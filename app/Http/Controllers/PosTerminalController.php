<?php

namespace App\Http\Controllers;

use App\Enums\OrderTypeEnum;
use App\Http\Requests\PosOrderItemsStoreRequest;
use App\Http\Requests\PosOrderOpenRequest;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\Pos\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * New Order / Terminal: pick a table (or takeaway) to open/resume an order,
 * then send item rounds to the kitchen from the order screen
 * (PosOrderController::show).
 */
class PosTerminalController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(): View
    {
        $this->authorize('create', Order::class);

        $tables = DiningTable::query()
            ->active()
            ->with('activeOrder')
            ->ordered()
            ->get()
            ->groupBy(fn (DiningTable $table) => $table->area ?: 'Tables');

        $takeawayOrders = Order::query()
            ->active()
            ->where('order_type', OrderTypeEnum::TAKEAWAY->value)
            ->latest('opened_at')
            ->get();

        return view('pos.terminal.index', [
            'page_title' => 'New Order / Terminal',
            'tables' => $tables,
            'takeawayOrders' => $takeawayOrders,
        ]);
    }

    public function open(PosOrderOpenRequest $request): RedirectResponse
    {
        [$order, $created] = $this->orders->open(
            OrderTypeEnum::from($request->input('order_type')),
            $request->integer('dining_table_id') ?: null,
            $request->integer('guest_count') ?: null,
            $request->input('general_note'),
            $request->user(),
        );

        return redirect()
            ->route('pos-orders.show', $order)
            ->with($created ? 'success' : 'info', $created
                ? "Order {$order->order_number} opened."
                : "{$order->displayTable()} already has a running order - opened it.");
    }

    public function addItems(PosOrderItemsStoreRequest $request, Order $order): JsonResponse
    {
        $this->authorize('addItems', $order);

        $items = $this->orders->addRound(
            $order,
            $request->validated('items'),
            $request->user(),
            $request->input('submission_key'),
        );

        return response()->json([
            'message' => $items->count().' item(s) sent to the kitchen.',
            'round_no' => $items->first()?->round_no,
        ]);
    }
}
