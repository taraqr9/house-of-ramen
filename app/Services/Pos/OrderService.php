<?php

namespace App\Services\Pos;

use App\Enums\DiscountTypeEnum;
use App\Enums\KitchenCancelReasonEnum;
use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\OrderTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Exceptions\PosException;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every POS order state change lives here - web controllers (and later the
 * REST API) only validate input, authorize, and call these methods.
 *
 * Concurrency rules: every mutation runs in a transaction and locks the
 * order row first (lockForUpdate) before touching its items/payments, so
 * two users working on the same order are serialized and totals are always
 * recalculated from the DB, never from browser input.
 */
class OrderService
{
    /**
     * Open a new order, or - for a dine-in table that already has a running
     * order - return that one instead. Returns [order, wasCreated].
     *
     * Two waiters tapping the same free table at once: the table row lock
     * serializes them, and orders.active_table_id's unique index is the
     * last line of defence; either way the second one lands on the order
     * the first one opened.
     *
     * @return array{0: Order, 1: bool}
     */
    public function open(OrderTypeEnum $type, ?int $tableId, ?int $guestCount, ?string $note, User $user): array
    {
        if ($type === OrderTypeEnum::DINE_IN && ! $tableId) {
            throw new PosException('Select a table for a dine-in order.');
        }

        try {
            return DB::transaction(function () use ($type, $tableId, $guestCount, $note, $user) {
                $table = null;

                if ($type === OrderTypeEnum::DINE_IN) {
                    $table = DiningTable::query()->whereKey($tableId)->lockForUpdate()->first();

                    if (! $table || ! $table->is_active) {
                        throw new PosException('This table is not available for orders.');
                    }

                    $existing = Order::query()->where('active_table_id', $table->id)->lockForUpdate()->first();

                    if ($existing) {
                        return [$existing, false];
                    }
                }

                $restaurant = Restaurant::query()->first();

                $order = Order::create([
                    'order_type' => $type,
                    'dining_table_id' => $table?->id,
                    'active_table_id' => $table?->id,
                    'table_name' => $table?->name,
                    'guest_count' => $guestCount,
                    'status' => OrderStatusEnum::OPEN,
                    'vat_percent' => $restaurant?->vat_percent ?? 0,
                    'service_charge_percent' => $restaurant?->service_charge_percent ?? 0,
                    'general_note' => $note,
                    'opened_by' => $user->id,
                    'opened_at' => now(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $order->update([
                    'order_number' => 'ORD-'.now()->format('ymd').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
                ]);

                return [$order, true];
            });
        } catch (UniqueConstraintViolationException) {
            // Lost the race on active_table_id - the other request's order
            // is committed now, so hand that one back.
            $existing = Order::query()->where('active_table_id', $tableId)->first();

            if (! $existing) {
                throw new PosException('Could not open the table, please try again.');
            }

            return [$existing, false];
        }
    }

    /**
     * Send a new round of items to the kitchen. Each line snapshots the menu
     * item's current name/price/category. The same dish twice with
     * different notes stays as two separate lines.
     *
     * $submissionKey (one per "Send to kitchen" click, generated client
     * side) makes a double click/resubmit a no-op instead of a duplicate
     * round.
     *
     * @param  list<array{menu_item_id: int, quantity: int, note?: string|null}>  $lines
     * @return Collection<int, OrderItem>
     */
    public function addRound(Order $order, array $lines, User $user, ?string $submissionKey = null): Collection
    {
        if (empty($lines)) {
            throw new PosException('Add at least one item before sending to the kitchen.');
        }

        $cacheKey = $submissionKey ? 'pos:round:'.$order->id.':'.$submissionKey : null;

        if ($cacheKey && ! Cache::add($cacheKey, true, now()->addMinutes(10))) {
            throw new PosException('This round was already sent to the kitchen.');
        }

        try {
            return DB::transaction(function () use ($order, $lines, $user) {
                $order = $this->lockActive($order);

                $menuItems = RestaurantMenuItem::query()
                    ->with('category')
                    ->whereIn('id', collect($lines)->pluck('menu_item_id')->unique())
                    ->get()
                    ->keyBy('id');

                $roundNo = (int) $order->items()->max('round_no') + 1;
                $now = now();
                $created = collect();

                foreach ($lines as $line) {
                    $menuItem = $menuItems->get($line['menu_item_id']);

                    // Soft-deleted items aren't returned at all; a hidden
                    // item or one in an inactive category is unavailable.
                    if (! $menuItem || ! $menuItem->is_available || ! $menuItem->category?->is_active) {
                        throw new PosException(($menuItem->name ?? 'An item').' is no longer available. Remove it and send again.');
                    }

                    $quantity = (int) $line['quantity'];

                    if ($quantity < 1) {
                        throw new PosException('Quantity must be at least 1.');
                    }

                    $created->push($order->items()->create([
                        'restaurant_menu_item_id' => $menuItem->id,
                        'restaurant_menu_category_id' => $menuItem->restaurant_menu_category_id,
                        'item_name' => $menuItem->name,
                        'category_name' => $menuItem->category?->name,
                        'quantity' => $quantity,
                        'unit_price' => $menuItem->price,
                        'line_total' => round((float) $menuItem->price * $quantity, 2),
                        'note' => filled($line['note'] ?? null) ? trim($line['note']) : null,
                        'round_no' => $roundNo,
                        'kitchen_status' => OrderItemStatusEnum::PENDING,
                        'sent_at' => $now,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]));
                }

                // More food after the bill was printed - the old bill is
                // stale, so the order goes back to running.
                if ($order->status === OrderStatusEnum::BILL_REQUESTED) {
                    $order->status = OrderStatusEnum::OPEN;
                    $order->bill_requested_at = null;
                }

                $order->updated_by = $user->id;
                $this->recalculate($order);

                return $created;
            });
        } catch (\Throwable $e) {
            if ($cacheKey) {
                Cache::forget($cacheKey);
            }

            throw $e;
        }
    }

    public function updateDetails(Order $order, ?int $guestCount, ?string $note, User $user): Order
    {
        return DB::transaction(function () use ($order, $guestCount, $note, $user) {
            $order = $this->lockActive($order);

            $order->update([
                'guest_count' => $guestCount,
                'general_note' => $note,
                'updated_by' => $user->id,
            ]);

            return $order;
        });
    }

    /**
     * Kitchen/serving status move: pending → preparing → ready → served,
     * one step at a time. Marking an already-served item served again is a
     * harmless no-op (two waiters tapping the same card); any other invalid
     * move is rejected.
     */
    public function transitionItem(OrderItem $item, OrderItemStatusEnum $to, User $user): OrderItem
    {
        return DB::transaction(function () use ($item, $to, $user) {
            $this->lockActive($item->order);

            $item = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($item->kitchen_status === $to && $to === OrderItemStatusEnum::SERVED) {
                return $item;
            }

            if (! $item->kitchen_status->canTransitionTo($to)) {
                throw new PosException("Cannot change {$item->item_name} from {$item->kitchen_status->label()} to {$to->label()}.");
            }

            $item->kitchen_status = $to;
            $item->updated_by = $user->id;

            if ($to === OrderItemStatusEnum::PREPARING) {
                $item->preparing_at = now();
            } elseif ($to === OrderItemStatusEnum::READY) {
                $item->ready_at = now();
            } elseif ($to === OrderItemStatusEnum::SERVED) {
                $item->served_at = now();
                $item->served_by = $user->id;
            }

            $item->save();

            return $item;
        });
    }

    /**
     * Reception/waiter has seen a Ready item (stops it flashing on the
     * Ready to Serve screen). Idempotent.
     */
    public function acknowledgeReady(OrderItem $item, User $user): OrderItem
    {
        return DB::transaction(function () use ($item, $user) {
            $item = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($item->kitchen_status === OrderItemStatusEnum::READY && ! $item->ready_acknowledged_at) {
                $item->update(['ready_acknowledged_at' => now(), 'updated_by' => $user->id]);
            }

            return $item;
        });
    }

    /**
     * Cancel a single item - never the whole order. Floor staff may cancel
     * anything not yet served; callers can narrow that with $allowedStatuses
     * (the kitchen may only reject pending/preparing items). The order is
     * recalculated immediately, and recalculate() rejects the cancellation
     * if completed payments would then exceed the new total.
     *
     * @param  list<OrderItemStatusEnum>|null  $allowedStatuses
     */
    public function cancelItem(OrderItem $item, string $reason, User $user, ?array $allowedStatuses = null): OrderItem
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new PosException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($item, $reason, $user, $allowedStatuses) {
            $order = $this->lockActive($item->order);

            $item = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($item->kitchen_status === OrderItemStatusEnum::CANCELLED) {
                throw new PosException("{$item->item_name} is already cancelled.");
            }

            if ($item->kitchen_status === OrderItemStatusEnum::SERVED) {
                throw new PosException("{$item->item_name} has already been served and cannot be cancelled.");
            }

            $allowed = $allowedStatuses ?? [OrderItemStatusEnum::PENDING, OrderItemStatusEnum::PREPARING, OrderItemStatusEnum::READY];

            if (! in_array($item->kitchen_status, $allowed, true)) {
                throw new PosException("{$item->item_name} is {$item->kitchen_status->label()} and can no longer be cancelled here.");
            }

            $item->update([
                'kitchen_status' => OrderItemStatusEnum::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
                'updated_by' => $user->id,
            ]);

            $order->updated_by = $user->id;
            $this->recalculate($order);

            return $item;
        });
    }

    /**
     * Kitchen rejection: pending/preparing only, with a predefined reason
     * ("Other" needs typed detail).
     */
    public function kitchenCancelItem(OrderItem $item, KitchenCancelReasonEnum $reason, ?string $detail, User $user): OrderItem
    {
        $detail = trim((string) $detail);

        if ($reason === KitchenCancelReasonEnum::OTHER && $detail === '') {
            throw new PosException('Please describe the reason.');
        }

        return $this->cancelItem(
            $item,
            'Kitchen: '.$reason->label().($detail !== '' ? ' - '.$detail : ''),
            $user,
            [OrderItemStatusEnum::PENDING, OrderItemStatusEnum::PREPARING],
        );
    }

    public function applyDiscount(Order $order, ?DiscountTypeEnum $type, float $value, User $user): Order
    {
        if ($type === DiscountTypeEnum::PERCENT && $value > 100) {
            throw new PosException('Discount percent cannot exceed 100.');
        }

        if ($value < 0) {
            throw new PosException('Discount cannot be negative.');
        }

        return DB::transaction(function () use ($order, $type, $value, $user) {
            $order = $this->lockActive($order);

            $order->discount_type = $value > 0 ? $type : null;
            $order->discount_value = $value > 0 ? $value : 0;
            $order->updated_by = $user->id;

            $this->recalculate($order);

            return $order;
        });
    }

    /**
     * Bill printed/handed to the customer. The order stays active and keeps
     * its table - only payment + completion releases it.
     */
    public function requestBill(Order $order, User $user): Order
    {
        return DB::transaction(function () use ($order, $user) {
            $order = $this->lockActive($order);

            if ($order->items()->where('kitchen_status', '!=', OrderItemStatusEnum::CANCELLED->value)->doesntExist()) {
                throw new PosException('This order has no items to bill.');
            }

            $this->recalculate($order);

            $order->update([
                'status' => OrderStatusEnum::BILL_REQUESTED,
                'bill_requested_at' => now(),
                'updated_by' => $user->id,
            ]);

            return $order;
        });
    }

    /**
     * Cancel a whole running order (e.g. customer left). Anything already
     * paid must be voided first, and a completed order can never be
     * cancelled. Totals are left as they were so reports can show the
     * value of what was cancelled.
     */
    public function cancel(Order $order, string $reason, User $user): Order
    {
        return DB::transaction(function () use ($order, $reason, $user) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatusEnum::COMPLETED) {
                throw new PosException('A completed order cannot be cancelled.');
            }

            if ($order->status === OrderStatusEnum::CANCELLED) {
                throw new PosException('This order is already cancelled.');
            }

            if ($order->payments()->where('status', PaymentStatusEnum::COMPLETED->value)->exists()) {
                throw new PosException('This order has payments. Void them before cancelling the order.');
            }

            $order->items()
                ->whereIn('kitchen_status', [
                    OrderItemStatusEnum::PENDING->value,
                    OrderItemStatusEnum::PREPARING->value,
                    OrderItemStatusEnum::READY->value,
                ])
                ->update([
                    'kitchen_status' => OrderItemStatusEnum::CANCELLED->value,
                    'cancelled_at' => now(),
                    'cancelled_by' => $user->id,
                    'cancellation_reason' => 'Order cancelled: '.$reason,
                    'updated_by' => $user->id,
                    'updated_at' => now(),
                ]);

            $order->update([
                'status' => OrderStatusEnum::CANCELLED,
                'active_table_id' => null,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
                'updated_by' => $user->id,
            ]);

            return $order;
        });
    }

    /**
     * Close a fully paid order and release its table. Blocked while any
     * item is still pending/preparing/ready - those must be served or
     * cancelled first, so nothing is left dangling on the kitchen screens.
     */
    public function complete(Order $order, User $user): Order
    {
        return DB::transaction(function () use ($order, $user) {
            $order = $this->lockActive($order);

            $this->recalculate($order);

            $outstanding = $order->items()
                ->whereIn('kitchen_status', [
                    OrderItemStatusEnum::PENDING->value,
                    OrderItemStatusEnum::PREPARING->value,
                    OrderItemStatusEnum::READY->value,
                ])
                ->count();

            if ($outstanding > 0) {
                throw new PosException("{$outstanding} item(s) are not served yet. Mark them served or cancel them before completing.");
            }

            if ($order->items()->where('kitchen_status', OrderItemStatusEnum::SERVED->value)->doesntExist()) {
                throw new PosException('This order has no served items. Cancel it instead.');
            }

            if (! $order->isFullyPaid()) {
                throw new PosException('Payment is incomplete. Amount due: '.number_format($order->balanceDue(), 2));
            }

            $order->update([
                'status' => OrderStatusEnum::COMPLETED,
                'active_table_id' => null,
                'completed_at' => now(),
                'completed_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return $order;
        });
    }

    /**
     * Rebuild every money column from the order's items and completed
     * payments. Must be called inside a transaction holding the order lock.
     * Refuses any change that would push the bill below what's already
     * been paid (cancelling a paid item, a bigger discount...) - void a
     * payment first.
     */
    public function recalculate(Order $order): Order
    {
        $subtotal = round((float) $order->items()
            ->where('kitchen_status', '!=', OrderItemStatusEnum::CANCELLED->value)
            ->sum('line_total'), 2);

        $discount = match ($order->discount_type) {
            DiscountTypeEnum::FIXED => min((float) $order->discount_value, $subtotal),
            DiscountTypeEnum::PERCENT => round($subtotal * (float) $order->discount_value / 100, 2),
            default => 0.0,
        };

        $taxable = round($subtotal - $discount, 2);
        $serviceCharge = round($taxable * (float) $order->service_charge_percent / 100, 2);
        $vat = round($taxable * (float) $order->vat_percent / 100, 2);
        $grandTotal = round($taxable + $serviceCharge + $vat, 2);

        $paid = round((float) $order->payments()->where('status', PaymentStatusEnum::COMPLETED->value)->sum('amount'), 2);

        if ($paid > $grandTotal) {
            throw new PosException('This change would make the bill ('.number_format($grandTotal, 2).') less than the amount already paid ('.number_format($paid, 2).'). Void a payment first.');
        }

        $order->fill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'service_charge' => $serviceCharge,
            'vat' => $vat,
            'grand_total' => $grandTotal,
            'paid_total' => $paid,
        ])->save();

        return $order;
    }

    /**
     * Re-read the order under a row lock and make sure it can still be
     * changed. Completed/cancelled orders are read-only history.
     */
    public function lockActive(Order $order): Order
    {
        $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

        if (! $order->isActive()) {
            throw new PosException("Order {$order->order_number} is {$order->status->label()} and can no longer be changed.");
        }

        return $order;
    }
}
