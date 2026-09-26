<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderItemStatusEnum;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\PosFeedRequest;
use App\Http\Resources\OrderItemResource;
use App\Models\OrderItem;
use App\Services\Pos\ItemFeedService;
use App\Services\Pos\OrderService;
use Illuminate\Http\JsonResponse;

class ServingController extends ApiController
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ItemFeedService $feed,
    ) {}

    /**
     * Ready items (+ item cancellations from the last 30 min on running
     * orders, so waiters can tell the table). Same ?since= cursor as the
     * kitchen feed.
     */
    public function feed(PosFeedRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('serving-view'), 403);

        return $this->ok($this->feed->feed([OrderItemStatusEnum::READY], $request->input('since'), 30));
    }

    public function acknowledge(OrderItem $orderItem): JsonResponse
    {
        $this->authorize('acknowledge', $orderItem);

        return $this->ok(OrderItemResource::make($this->orders->acknowledgeReady($orderItem, auth()->user())), 'Acknowledged.');
    }

    /**
     * ready → served. Repeating it for an already-served item is a safe
     * no-op (200).
     */
    public function served(OrderItem $orderItem): JsonResponse
    {
        $this->authorize('transition', [$orderItem, OrderItemStatusEnum::SERVED]);

        $item = $this->orders->transitionItem($orderItem, OrderItemStatusEnum::SERVED, auth()->user());

        return $this->ok(OrderItemResource::make($item->load('servedBy')), 'Served.');
    }
}
