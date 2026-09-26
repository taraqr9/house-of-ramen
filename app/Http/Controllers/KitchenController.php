<?php

namespace App\Http\Controllers;

use App\Enums\KitchenCancelReasonEnum;
use App\Enums\OrderItemStatusEnum;
use App\Http\Requests\KitchenItemCancelRequest;
use App\Http\Requests\KitchenStatusUpdateRequest;
use App\Http\Requests\PosFeedRequest;
use App\Models\OrderItem;
use App\Services\Pos\ItemFeedService;
use App\Services\Pos\MenuAvailabilityService;
use App\Services\Pos\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class KitchenController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ItemFeedService $feed,
        private readonly MenuAvailabilityService $menuAvailability,
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('kitchen-view'), 403);

        return view('pos.kitchen.index', [
            'page_title' => 'Kitchen',
            'cancelReasons' => KitchenCancelReasonEnum::options(),
        ]);
    }

    public function feed(PosFeedRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('kitchen-view'), 403);

        return response()->json($this->feed->feed(
            [OrderItemStatusEnum::PENDING, OrderItemStatusEnum::PREPARING],
            $request->input('since'),
        ));
    }

    public function update(KitchenStatusUpdateRequest $request, OrderItem $order_item): JsonResponse
    {
        $status = OrderItemStatusEnum::from($request->input('status'));

        $this->authorize('transition', [$order_item, $status]);

        $item = $this->orders->transitionItem($order_item, $status, $request->user());

        return response()->json(['id' => $item->id, 'status' => $item->kitchen_status->value]);
    }

    /**
     * Reject one item (out of stock etc.). Only that item is cancelled; the
     * response says whether offering "mark unavailable" makes sense.
     */
    public function cancel(KitchenItemCancelRequest $request, OrderItem $order_item): JsonResponse
    {
        $this->authorize('kitchenCancel', $order_item);

        $reason = KitchenCancelReasonEnum::from($request->input('reason'));

        $item = $this->orders->kitchenCancelItem($order_item, $reason, $request->input('reason_detail'), $request->user());

        $menuItem = $item->menuItem;

        return response()->json([
            'id' => $item->id,
            'status' => $item->kitchen_status->value,
            'message' => "{$item->item_name} cancelled.",
            'offer_mark_unavailable' => $reason->suggestsUnavailable()
                && $menuItem && ! $menuItem->trashed() && $menuItem->is_available
                && $request->user()->can('update', $menuItem),
        ]);
    }

    /**
     * Separate, explicit action: take the dish behind this order item off
     * the menu. Never triggered by cancelling.
     */
    public function markUnavailable(OrderItem $order_item): JsonResponse
    {
        $menuItem = $order_item->menuItem;

        abort_if(! $menuItem || $menuItem->trashed(), 404);

        $this->authorize('update', $menuItem);

        $this->menuAvailability->markUnavailable($menuItem, auth()->user());

        return response()->json(['message' => "{$menuItem->name} is now unavailable on the menu.", 'is_available' => false]);
    }
}
