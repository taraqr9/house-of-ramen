<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DiscountTypeEnum;
use App\Enums\OrderTypeEnum;
use App\Exceptions\DuplicateSubmissionException;
use App\Filters\ActiveOrderIndexFilter;
use App\Filters\CompletedOrderIndexFilter;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\ActiveOrderIndexRequest;
use App\Http\Requests\Api\ApiAddRoundRequest;
use App\Http\Requests\CompletedOrderIndexRequest;
use App\Http\Requests\OrderItemCancelRequest;
use App\Http\Requests\PosDiscountRequest;
use App\Http\Requests\PosOrderCancelRequest;
use App\Http\Requests\PosOrderOpenRequest;
use App\Http\Requests\PosOrderUpdateRequest;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Pos\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orders for the POS app: open, rounds, bill, discount, cancel, complete,
 * history. Same FormRequests, policies and OrderService as the web POS.
 */
class OrderController extends ApiController
{
    public function __construct(private readonly OrderService $orders) {}

    public function store(PosOrderOpenRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        [$order, $created] = $this->orders->open(
            OrderTypeEnum::from($request->input('order_type')),
            $request->integer('dining_table_id') ?: null,
            $request->integer('guest_count') ?: null,
            $request->input('general_note'),
            $request->user(),
        );

        // An already-running order on that table is returned (200), not an error.
        return $this->ok(
            $this->full($order),
            $created ? 'Order opened.' : 'This table already has a running order.',
            $created ? 201 : 200,
            ['created' => $created],
        );
    }

    public function active(ActiveOrderIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $orders = ActiveOrderIndexFilter::applyFilters(Order::query()->active(), $request)
            ->orderBy('opened_at')
            ->paginate(50);

        return $this->ok(OrderResource::collection($orders));
    }

    public function completed(CompletedOrderIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $orders = CompletedOrderIndexFilter::applyFilters(Order::query()->with(['payments', 'completedBy', 'cancelledBy']), $request)
            ->orderByDesc('completed_at')
            ->orderByDesc('cancelled_at')
            ->paginate(30);

        return $this->ok(OrderResource::collection($orders));
    }

    /**
     * Current state of one order. Like the web order screen: order-view, or
     * order-create for a running order (waiters taking orders).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('view', $order) || ($order->isActive() && $user->can('addItems', $order)), 403);

        return $this->ok($this->full($order));
    }

    /**
     * Read-only full history (rounds, notes, kitchen timeline, cancellations,
     * payments incl. voided, totals).
     */
    public function history(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return $this->ok($this->full($order));
    }

    public function update(PosOrderUpdateRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $order = $this->orders->updateDetails($order, $request->integer('guest_count') ?: null, $request->input('general_note'), $request->user());

        return $this->ok($this->full($order), 'Order updated.');
    }

    /**
     * Send a round. A retry with the same submission_key returns the order
     * with duplicate=true (200) instead of adding the items again.
     */
    public function addRound(ApiAddRoundRequest $request, Order $order): JsonResponse
    {
        $this->authorize('addItems', $order);

        try {
            $items = $this->orders->addRound($order, $request->validated('items'), $request->user(), $request->input('submission_key'));
        } catch (DuplicateSubmissionException) {
            return $this->ok($this->full($order), 'This round was already received.', 200, ['duplicate' => true]);
        }

        return $this->ok($this->full($order), $items->count().' item(s) sent to the kitchen.', 201, [
            'duplicate' => false,
            'round_no' => $items->first()?->round_no,
        ]);
    }

    public function requestBill(Order $order): JsonResponse
    {
        $this->authorize('bill', $order);

        $order = $this->orders->requestBill($order, auth()->user());

        return $this->ok($this->full($order), 'Bill generated.');
    }

    public function cancel(PosOrderCancelRequest $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $order = $this->orders->cancel($order, $request->input('reason'), $request->user());

        return $this->ok($this->full($order), 'Order cancelled.');
    }

    /**
     * Floor-staff cancellation of one item (order_item-cancel; pending,
     * preparing or ready). Kitchen rejections use /kitchen cancel instead.
     */
    public function cancelItem(OrderItemCancelRequest $request, OrderItem $orderItem): JsonResponse
    {
        $this->authorize('cancel', $orderItem);

        $item = $this->orders->cancelItem($orderItem, $request->input('reason'), $request->user());

        return $this->ok([
            'item' => OrderItemResource::make($item->load('cancelledBy'))->resolve($request),
            'order' => $this->full($item->order)->resolve($request),
        ], "{$item->item_name} cancelled.");
    }

    /**
     * The bill: items, totals, payments and whether it can be completed now
     * (reasons come from OrderService::completionBlockers - not recomputed).
     */
    public function billing(Order $order): JsonResponse
    {
        $this->authorize('bill', $order);

        $blockers = $this->orders->completionBlockers($order);
        $order = $this->full($order);

        return $this->ok($order, 'OK', 200, [
            'completion' => [
                'allowed' => $blockers === [],
                'blockers' => $blockers,
                'outstanding_items' => $order->resource->items->filter(fn ($i) => $i->kitchen_status->isOutstanding())->count(),
            ],
        ]);
    }

    public function discount(PosDiscountRequest $request, Order $order): JsonResponse
    {
        $this->authorize('discount', $order);

        $order = $this->orders->applyDiscount(
            $order,
            DiscountTypeEnum::from($request->input('discount_type')),
            (float) $request->input('discount_value', 0),
            $request->user(),
        );

        return $this->ok($this->full($order), 'Discount updated.');
    }

    public function complete(Order $order): JsonResponse
    {
        $this->authorize('complete', $order);

        $order = $this->orders->complete($order, auth()->user());

        return $this->ok($this->full($order), "Order {$order->order_number} completed. Table released.");
    }

    private function full(Order $order): OrderResource
    {
        return OrderResource::make($order->fresh([
            'items.servedBy', 'items.cancelledBy', 'payments.receivedBy', 'payments.voidedBy',
            'openedBy', 'completedBy', 'cancelledBy',
        ]));
    }
}
