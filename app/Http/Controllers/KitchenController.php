<?php

namespace App\Http\Controllers;

use App\Enums\OrderItemStatusEnum;
use App\Http\Requests\KitchenStatusUpdateRequest;
use App\Http\Requests\PosFeedRequest;
use App\Models\OrderItem;
use App\Services\Pos\ItemFeedService;
use App\Services\Pos\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class KitchenController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ItemFeedService $feed,
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('kitchen-view'), 403);

        return view('pos.kitchen.index', ['page_title' => 'Kitchen']);
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
}
