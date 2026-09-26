<?php

namespace App\Http\Controllers;

use App\Enums\OrderItemStatusEnum;
use App\Http\Requests\PosFeedRequest;
use App\Models\OrderItem;
use App\Services\Pos\ItemFeedService;
use App\Services\Pos\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Ready to Serve: items the kitchen has marked Ready, until a waiter marks
 * them Served.
 */
class ServingController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ItemFeedService $feed,
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('serving-view'), 403);

        return view('pos.serving.index', ['page_title' => 'Ready to Serve']);
    }

    public function feed(PosFeedRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('serving-view'), 403);

        return response()->json($this->feed->feed([OrderItemStatusEnum::READY], $request->input('since')));
    }

    public function serve(OrderItem $order_item): JsonResponse
    {
        $this->authorize('transition', [$order_item, OrderItemStatusEnum::SERVED]);

        $item = $this->orders->transitionItem($order_item, OrderItemStatusEnum::SERVED, auth()->user());

        return response()->json(['id' => $item->id, 'status' => $item->kitchen_status->value]);
    }

    public function acknowledge(OrderItem $order_item): JsonResponse
    {
        $this->authorize('acknowledge', $order_item);

        $item = $this->orders->acknowledgeReady($order_item, auth()->user());

        return response()->json(['id' => $item->id, 'acknowledged' => $item->ready_acknowledged_at !== null]);
    }
}
