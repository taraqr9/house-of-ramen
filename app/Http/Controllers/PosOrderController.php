<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatusEnum;
use App\Enums\OrderTypeEnum;
use App\Filters\ActiveOrderIndexFilter;
use App\Filters\CompletedOrderIndexFilter;
use App\Http\Requests\ActiveOrderIndexRequest;
use App\Http\Requests\CompletedOrderIndexRequest;
use App\Http\Requests\OrderItemCancelRequest;
use App\Http\Requests\PosOrderCancelRequest;
use App\Http\Requests\PosOrderUpdateRequest;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantMenuCategory;
use App\Models\User;
use App\Services\Pos\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function active(ActiveOrderIndexRequest $request): View
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::query()->active()->withCount([
            'items as pending_count' => fn ($q) => $q->whereIn('kitchen_status', ['pending', 'preparing']),
            'items as ready_count' => fn ($q) => $q->where('kitchen_status', 'ready'),
        ]);

        $orders = ActiveOrderIndexFilter::applyFilters($query, $request)
            ->orderBy('opened_at')
            ->paginate(30)
            ->appends($request->query());

        return view('pos.orders.active', [
            'page_title' => 'Active Orders',
            'orders' => $orders,
            'tables' => DiningTable::query()->ordered()->get(['id', 'name']),
            'types' => OrderTypeEnum::options(),
            'statuses' => collect(OrderStatusEnum::activeCases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
        ]);
    }

    public function completed(CompletedOrderIndexRequest $request): View
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::query()->with(['completedPayments', 'completedBy']);

        $orders = CompletedOrderIndexFilter::applyFilters($query, $request)
            ->orderByDesc('completed_at')
            ->orderByDesc('cancelled_at')
            ->paginate(30)
            ->appends($request->query());

        return view('pos.orders.completed', [
            'page_title' => 'Completed Orders',
            'orders' => $orders,
            'tables' => DiningTable::withTrashed()->ordered()->get(['id', 'name']),
            'cashiers' => User::query()->whereIn('id', Order::query()->whereNotNull('completed_by')->distinct()->select('completed_by'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Running order → the terminal/order screen (add rounds, see kitchen
     * progress). Closed order → read-only history.
     */
    public function show(Order $order): View
    {
        // Waiters taking orders (order-create) need the running order's
        // screen; closed-order history needs order-view.
        $user = auth()->user();
        abort_unless($user->can('view', $order) || ($order->isActive() && $user->can('addItems', $order)), 403);

        $order->load(['items.servedBy', 'items.cancelledBy', 'payments.receivedBy', 'payments.voidedBy', 'openedBy', 'completedBy', 'cancelledBy']);

        if (! $order->isActive()) {
            return view('pos.orders.show', [
                'page_title' => 'Order '.$order->order_number,
                'order' => $order,
            ]);
        }

        $categories = RestaurantMenuCategory::query()
            ->where('is_active', true)
            ->with(['menuItems' => fn ($q) => $q->where('is_available', true)])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category) => $category->menuItems->isNotEmpty())
            ->values();

        return view('pos.orders.terminal', [
            'page_title' => 'Order '.$order->order_number,
            'order' => $order,
            'categories' => $categories,
        ]);
    }

    public function update(PosOrderUpdateRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $this->orders->updateDetails(
            $order,
            $request->integer('guest_count') ?: null,
            $request->input('general_note'),
            $request->user(),
        );

        return redirect()->route('pos-orders.show', $order)->with('success', 'Order updated.');
    }

    public function cancel(PosOrderCancelRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $this->orders->cancel($order, $request->input('reason'), $request->user());

        return redirect()->route('pos-orders.active')->with('success', "Order {$order->order_number} cancelled.");
    }

    public function cancelItem(OrderItemCancelRequest $request, OrderItem $order_item): JsonResponse|RedirectResponse
    {
        $this->authorize('cancel', $order_item);

        $item = $this->orders->cancelItem($order_item, $request->input('reason'), $request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => "{$item->item_name} cancelled.", 'status' => $item->kitchen_status->value]);
        }

        return redirect()->back()->with('success', "{$item->item_name} cancelled.");
    }
}
