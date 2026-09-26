<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Filters\PaymentIndexFilter;
use App\Http\Requests\PaymentIndexRequest;
use App\Http\Requests\PaymentStoreRequest;
use App\Http\Requests\PaymentVoidRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Pos\OrderService;
use App\Services\Pos\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly OrderService $orders,
    ) {}

    public function index(PaymentIndexRequest $request): View
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()->with(['order', 'receivedBy']);
        $filtered = PaymentIndexFilter::applyFilters($query, $request);

        $totals = (clone $filtered)
            ->where('status', PaymentStatusEnum::COMPLETED->value)
            ->reorder()
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->keyBy(fn ($row) => $row->payment_method->value);

        $payments = $filtered
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->appends($request->query());

        return view('pos.payments.index', [
            'page_title' => 'Payments',
            'payments' => $payments,
            'totals' => $totals,
            'methods' => PaymentMethodEnum::options(),
            'cashiers' => User::query()->whereIn('id', Payment::query()->whereNotNull('received_by')->distinct()->select('received_by'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Take a payment from the billing screen. Once the bill is fully paid
     * and everything's served, the order is completed right away (if the
     * cashier may complete orders) so the table frees up without an extra
     * click; otherwise it waits for the Complete button.
     */
    public function store(PaymentStoreRequest $request, Order $order): RedirectResponse
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

        $message = $payment->payment_method->label().' payment of '.number_format($payment->amount, 2).' recorded.';

        if ((float) $payment->change_amount > 0) {
            $message .= ' Give change: '.number_format($payment->change_amount, 2).'.';
        }

        if ($request->user()->can('complete', $order)) {
            $result = $this->orders->tryComplete($order, $request->user());

            if ($result['completed']) {
                return redirect()->route('pos-orders.show', $order)->with('success', $message.' Order completed, table released.');
            }

            if ($result['reason']) {
                return redirect()->route('pos-billing.show', $order)->with('warning', $message.' Not completed yet: '.$result['reason']);
            }
        }

        return redirect()->route('pos-billing.show', $order)->with('success', $message);
    }

    public function void(PaymentVoidRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('void', $payment);

        $this->payments->void($payment, $request->input('reason'), $request->user());

        return redirect()->route('pos-billing.show', $payment->order_id)->with('success', 'Payment voided.');
    }
}
