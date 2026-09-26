<?php

namespace App\Http\Controllers;

use App\Enums\DiscountTypeEnum;
use App\Enums\OrderItemStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Http\Requests\PosDiscountRequest;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\Pos\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Billing: review the bill, apply a discount, print it, take payments and
 * complete the order - all from one screen so the cashier never has to
 * hop between pages.
 */
class PosBillingController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('billing-view'), 403);

        $orders = Order::query()
            ->active()
            ->withCount(['items as outstanding_count' => fn ($q) => $q->whereIn('kitchen_status', [
                OrderItemStatusEnum::PENDING->value,
                OrderItemStatusEnum::PREPARING->value,
                OrderItemStatusEnum::READY->value,
            ])])
            // Customers waiting on a bill first.
            ->orderByRaw('bill_requested_at IS NULL')
            ->orderBy('bill_requested_at')
            ->orderBy('opened_at')
            ->get();

        return view('pos.billing.index', [
            'page_title' => 'Billing',
            'orders' => $orders,
        ]);
    }

    public function show(Order $order): View|RedirectResponse
    {
        $this->authorize('bill', $order);

        if (! $order->isActive()) {
            return redirect()->route('pos-orders.show', $order);
        }

        $order->load(['items', 'payments.receivedBy', 'payments.voidedBy']);

        return view('pos.billing.show', [
            'page_title' => 'Bill '.$order->order_number,
            'order' => $order,
            'billItems' => $order->items->reject(fn ($item) => $item->isCancelled()),
            'outstanding' => $order->items->filter(fn ($item) => $item->kitchen_status->isOutstanding()),
            'discountTypes' => DiscountTypeEnum::options(),
            'paymentMethods' => PaymentMethodEnum::options(),
        ]);
    }

    /**
     * Browser-printable receipt (80mm thermal or A4). Same lines are
     * merged (e.g. two rounds of the same dish at the same price).
     */
    public function print(Order $order): View
    {
        $this->authorize('bill', $order);

        $order->load(['items', 'completedPayments', 'completedBy', 'openedBy']);

        $lines = $order->items
            ->reject(fn ($item) => $item->isCancelled())
            ->groupBy(fn ($item) => $item->item_name.'|'.$item->unit_price)
            ->map(fn ($group) => (object) [
                'name' => $group->first()->item_name,
                'unit_price' => (float) $group->first()->unit_price,
                'quantity' => $group->sum('quantity'),
                'total' => (float) $group->sum('line_total'),
            ])
            ->values();

        return view('pos.billing.print', [
            'order' => $order,
            'lines' => $lines,
            'restaurant' => Restaurant::query()->first(),
        ]);
    }

    public function discount(PosDiscountRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('discount', $order);

        $this->orders->applyDiscount(
            $order,
            DiscountTypeEnum::from($request->input('discount_type')),
            (float) $request->input('discount_value', 0),
            $request->user(),
        );

        return redirect()->route('pos-billing.show', $order)->with('success', 'Discount updated.');
    }

    public function requestBill(Order $order): RedirectResponse
    {
        $this->authorize('bill', $order);

        $this->orders->requestBill($order, auth()->user());

        return redirect()->route('pos-billing.show', ['order' => $order, 'print' => 1])->with('success', 'Bill generated.');
    }

    public function complete(Order $order): RedirectResponse
    {
        $this->authorize('complete', $order);

        $this->orders->complete($order, auth()->user());

        return redirect()->route('pos-orders.show', $order)->with('success', "Order {$order->order_number} completed. Table released.");
    }
}
