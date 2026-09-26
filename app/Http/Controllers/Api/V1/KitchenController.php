<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KitchenCancelReasonEnum;
use App\Enums\OrderItemStatusEnum;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\KitchenItemCancelRequest;
use App\Http\Requests\PosFeedRequest;
use App\Http\Resources\OrderItemResource;
use App\Models\OrderItem;
use App\Services\Pos\ItemFeedService;
use App\Services\Pos\MenuAvailabilityService;
use App\Services\Pos\OrderService;
use Illuminate\Http\JsonResponse;

class KitchenController extends ApiController
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ItemFeedService $feed,
        private readonly MenuAvailabilityService $menuAvailability,
    ) {}

    /**
     * Pending/preparing items. Pass the previous response's server_time as
     * ?since= to get only changes (items in any status, so the app can drop
     * ones that left the kitchen). full=true means "replace your list".
     */
    public function feed(PosFeedRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('kitchen-view'), 403);

        return $this->ok($this->feed->feed(
            [OrderItemStatusEnum::PENDING, OrderItemStatusEnum::PREPARING],
            $request->input('since'),
        ));
    }

    public function start(OrderItem $orderItem): JsonResponse
    {
        return $this->transition($orderItem, OrderItemStatusEnum::PREPARING, 'Preparing.');
    }

    public function ready(OrderItem $orderItem): JsonResponse
    {
        return $this->transition($orderItem, OrderItemStatusEnum::READY, 'Ready to serve.');
    }

    /**
     * Kitchen rejection of one item (pending/preparing only, predefined
     * reason; "other" needs reason_detail). Never cancels the whole order.
     */
    public function cancel(KitchenItemCancelRequest $request, OrderItem $orderItem): JsonResponse
    {
        $this->authorize('kitchenCancel', $orderItem);

        $reason = KitchenCancelReasonEnum::from($request->input('reason'));
        $item = $this->orders->kitchenCancelItem($orderItem, $reason, $request->input('reason_detail'), $request->user());
        $menuItem = $item->menuItem;

        return $this->ok(OrderItemResource::make($item->load('cancelledBy')), "{$item->item_name} cancelled.", 200, [
            'offer_mark_unavailable' => $reason->suggestsUnavailable()
                && $menuItem && ! $menuItem->trashed() && $menuItem->is_available
                && $request->user()->can('update', $menuItem),
        ]);
    }

    /**
     * Separate, explicit action (restaurant_menu_item-edit): take the dish
     * behind this order item off the menu.
     */
    public function markMenuUnavailable(OrderItem $orderItem): JsonResponse
    {
        $menuItem = $orderItem->menuItem;
        abort_if(! $menuItem || $menuItem->trashed(), 404);

        $this->authorize('update', $menuItem);

        $this->menuAvailability->markUnavailable($menuItem, auth()->user());

        return $this->ok(['menu_item_id' => $menuItem->id, 'is_available' => false], "{$menuItem->name} is now unavailable on the menu.");
    }

    private function transition(OrderItem $orderItem, OrderItemStatusEnum $to, string $message): JsonResponse
    {
        $this->authorize('transition', [$orderItem, $to]);

        $item = $this->orders->transitionItem($orderItem, $to, auth()->user());

        return $this->ok(OrderItemResource::make($item), $message);
    }
}
