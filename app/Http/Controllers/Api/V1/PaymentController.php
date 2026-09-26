<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethodEnum;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\ApiPaymentStoreRequest;
use App\Http\Requests\PaymentVoidRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Pos\OrderService;
use App\Services\Pos\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends ApiController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly OrderService $orders,
    ) {}

    public function index(Order $order): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        return $this->ok(PaymentResource::collection($order->payments()->with(['receivedBy', 'voidedBy'])->get()));
    }

    /**
     * Record a payment (cash/card; split = several calls). Retrying with the
     * same idempotency_key returns the original payment (200, duplicate=true).
     * Like the web, a payment that settles the bill completes the order when
     * the user may complete orders and everything is served.
     */
    public function store(ApiPaymentStoreRequest $request, Order $order): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $payment = $this->payments->record(
            $order,
            PaymentMethodEnum::from($request->input('payment_method')),
            (float) $request->input('amount'),
            $request->user(),
            $request->input('reference_no'),
            $request->input('remarks'),
            $request->input('idempotency_key'),
        );

        $duplicate = ! $payment->wasRecentlyCreated;

        $completion = ['completed' => false, 'reason' => null];
        if ($request->user()->can('complete', $order)) {
            $completion = $this->orders->tryComplete($order, $request->user());
        }

        $message = $duplicate ? 'This payment was already recorded.' : $payment->payment_method->label().' payment of '.number_format((float) $payment->amount, 2).' recorded.';

        return $this->ok([
            'payment' => PaymentResource::make($payment->load('receivedBy'))->resolve($request),
            'order' => $this->orderResource($order)->resolve($request),
        ], $message, $duplicate ? 200 : 201, [
            'duplicate' => $duplicate,
            'order_completed' => $completion['completed'],
            'completion_blocked_reason' => $completion['reason'],
        ]);
    }

    public function void(PaymentVoidRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('void', $payment);

        $payment = $this->payments->void($payment, $request->input('reason'), $request->user());

        return $this->ok([
            'payment' => PaymentResource::make($payment->load(['receivedBy', 'voidedBy']))->resolve($request),
            'order' => $this->orderResource($payment->order)->resolve($request),
        ], 'Payment voided.');
    }

    private function orderResource(Order $order): OrderResource
    {
        return OrderResource::make($order->fresh(['payments.receivedBy', 'payments.voidedBy', 'completedBy']));
    }
}
