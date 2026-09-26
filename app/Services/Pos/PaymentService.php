<?php

namespace App\Services\Pos;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Exceptions\PosException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Records and voids payments. One order can take several payments (split /
 * mixed cash + card); together they may never exceed the grand total.
 * Cash is the only method with change: handing over 3,000 for a 2,500 due
 * records a 2,500 payment with 500 change, so overpayment is never stored.
 */
class PaymentService
{
    public function __construct(private readonly OrderService $orders) {}

    public function record(
        Order $order,
        PaymentMethodEnum $method,
        float $amount,
        User $user,
        ?string $referenceNo = null,
        ?string $remarks = null,
        ?string $idempotencyKey = null,
    ): Payment {
        if ($amount <= 0) {
            throw new PosException('Payment amount must be greater than zero.');
        }

        if ($idempotencyKey && ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $this->replay($existing, $order);
        }

        try {
            return DB::transaction(function () use ($order, $method, $amount, $user, $referenceNo, $remarks, $idempotencyKey) {
                $order = $this->orders->lockActive($order);
                $this->orders->recalculate($order);

                $due = $order->balanceDue();

                if ($due <= 0) {
                    throw new PosException('This order is already fully paid.');
                }

                $amount = round($amount, 2);
                $applied = $amount;
                $change = 0.0;

                if ($amount > $due) {
                    if ($method !== PaymentMethodEnum::CASH) {
                        throw new PosException('Card amount ('.number_format($amount, 2).') is more than the amount due ('.number_format($due, 2).').');
                    }

                    $applied = $due;
                    $change = round($amount - $due, 2);
                }

                $payment = $order->payments()->create([
                    'payment_method' => $method,
                    'amount' => $applied,
                    'tendered_amount' => $method === PaymentMethodEnum::CASH ? $amount : null,
                    'change_amount' => $change,
                    'reference_no' => $referenceNo,
                    'remarks' => $remarks,
                    'status' => PaymentStatusEnum::COMPLETED,
                    'idempotency_key' => $idempotencyKey,
                    'received_by' => $user->id,
                    'paid_at' => now(),
                ]);

                $this->orders->recalculate($order);

                return $payment;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two identical submissions raced past the first check - the
            // other one won, return it.
            if ($idempotencyKey && ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first())) {
                return $this->replay($existing, $order);
            }

            throw $e;
        }
    }

    /**
     * Void a mistaken payment on a still-running order. The row stays, so
     * the audit trail is permanent. Payments on completed orders are final.
     */
    public function void(Payment $payment, string $reason, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $user) {
            $order = $this->orders->lockActive($payment->order);

            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatusEnum::VOIDED) {
                throw new PosException('This payment is already voided.');
            }

            $payment->update([
                'status' => PaymentStatusEnum::VOIDED,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $reason,
            ]);

            $this->orders->recalculate($order);

            return $payment;
        });
    }

    /**
     * A repeated idempotency key returns the payment it created (the caller
     * can tell via $payment->wasRecentlyCreated === false) - but only for the
     * same order; reusing a key on another order is a client bug.
     */
    private function replay(Payment $existing, Order $order): Payment
    {
        if ($existing->order_id !== $order->id) {
            throw new PosException('This idempotency key was already used for a different order.');
        }

        return $existing;
    }
}
