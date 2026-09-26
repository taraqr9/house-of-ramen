<?php

use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Exceptions\PosException;
use App\Models\Order;
use App\Models\User;
use App\Services\Pos\OrderService;
use App\Services\Pos\PaymentService;
use App\Services\Pos\PosReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Open a dine-in order with one line per [price, qty] and return it.
 */
function kitchenOrder($user, array $lines, $category = null): Order
{
    $category ??= posSetup();
    [$order] = app(OrderService::class)->open(OrderTypeEnum::DINE_IN, posTable()->id, null, null, $user);

    app(OrderService::class)->addRound($order, array_map(fn ($line) => [
        'menu_item_id' => posMenuItem($category, $line[0])->id,
        'quantity' => $line[1],
    ], $lines), $user);

    return $order->fresh();
}

function cook(): User
{
    return adminUser(['kitchen-view', 'kitchen-update', 'kitchen-cancel']);
}

it('cancels a pending item from the kitchen and recalculates totals', function () {
    $user = posUser();
    $order = kitchenOrder($user, [[500, 2], [300, 1]]);
    [$ramen, $gyoza] = $order->items;
    $cook = cook();

    $this->actingAs($cook)->patchJson(route('pos-kitchen.cancel', $ramen), ['reason' => 'out_of_stock'])
        ->assertOk()->assertJson(['status' => 'cancelled']);

    $ramen->refresh();
    expect($ramen->kitchen_status)->toBe(OrderItemStatusEnum::CANCELLED)
        ->and($ramen->cancelled_by)->toBe($cook->id)
        ->and($ramen->cancelled_at)->not->toBeNull()
        ->and($ramen->cancellation_reason)->toBe('Kitchen: Out of stock')
        // Only that item - the order and its other item keep going.
        ->and($gyoza->fresh()->kitchen_status)->toBe(OrderItemStatusEnum::PENDING)
        ->and($order->fresh()->isActive())->toBeTrue()
        ->and((float) $order->fresh()->subtotal)->toBe(300.0)
        ->and((float) $order->fresh()->grand_total)->toBe(300.0);

    // Bill shows it as not charged, with who/why.
    $this->actingAs($user)->get(route('pos-billing.show', $order))->assertOk()
        ->assertSee('Cancelled - not charged')->assertSee('Kitchen: Out of stock')->assertSee($cook->name);
});

it('cancels a preparing item with an "Other" reason and its detail', function () {
    $user = posUser();
    $order = kitchenOrder($user, [[450, 1]]);
    $item = $order->items->first();
    app(OrderService::class)->transitionItem($item, OrderItemStatusEnum::PREPARING, $user);

    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $item), ['reason' => 'other', 'reason_detail' => 'Dropped on the floor'])
        ->assertOk();

    expect($item->fresh()->kitchen_status)->toBe(OrderItemStatusEnum::CANCELLED)
        ->and($item->fresh()->cancellation_reason)->toBe('Kitchen: Other - Dropped on the floor')
        ->and((float) $order->fresh()->grand_total)->toBe(0.0);
});

it('does not let the kitchen cancel ready or served items', function () {
    $user = posUser();
    $order = kitchenOrder($user, [[200, 1], [300, 1]]);
    [$ready, $served] = $order->items;
    $service = app(OrderService::class);

    $service->transitionItem($ready, OrderItemStatusEnum::PREPARING, $user);
    $service->transitionItem($ready->fresh(), OrderItemStatusEnum::READY, $user);
    foreach ([OrderItemStatusEnum::PREPARING, OrderItemStatusEnum::READY, OrderItemStatusEnum::SERVED] as $status) {
        $service->transitionItem($served->fresh(), $status, $user);
    }

    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $ready), ['reason' => 'unable_to_prepare'])->assertStatus(422);
    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $served), ['reason' => 'unable_to_prepare'])
        ->assertStatus(422)->assertJsonFragment(['message' => "{$served->item_name} has already been served and cannot be cancelled."]);

    // Floor staff can't cancel a served item either.
    $this->actingAs($user)->patchJson(route('pos-order-items.cancel', $served), ['reason' => 'x'])->assertStatus(422);

    expect($ready->fresh()->kitchen_status)->toBe(OrderItemStatusEnum::READY)
        ->and($served->fresh()->kitchen_status)->toBe(OrderItemStatusEnum::SERVED)
        ->and((float) $order->fresh()->subtotal)->toBe(500.0);
});

it('prevents cancelling the same item twice', function () {
    $user = posUser();
    $item = kitchenOrder($user, [[200, 1], [100, 1]])->items->first();

    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $item), ['reason' => 'duplicate_item'])->assertOk();
    $firstCancelledAt = $item->fresh()->cancelled_at;
    $this->travel(1)->minutes();

    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $item), ['reason' => 'duplicate_item'])
        ->assertStatus(422)->assertJsonFragment(['message' => "{$item->item_name} is already cancelled."]);

    expect($item->fresh()->cancelled_at->equalTo($firstCancelledAt))->toBeTrue();
});

it('rejects a cancellation that would leave payments above the new total', function () {
    $user = posUser();
    $order = kitchenOrder($user, [[800, 1], [200, 1]]);
    $cheap = $order->items->firstWhere('unit_price', 200);
    app(PaymentService::class)->record($order, PaymentMethodEnum::CARD, 900, $user);

    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $cheap), ['reason' => 'out_of_stock'])
        ->assertStatus(422)->assertJsonFragment(['message' => 'This change would make the bill (800.00) less than the amount already paid (900.00). Void a payment first.']);

    expect($cheap->fresh()->kitchen_status)->toBe(OrderItemStatusEnum::PENDING)
        ->and($cheap->fresh()->cancelled_at)->toBeNull()
        ->and((float) $order->fresh()->grand_total)->toBe(1000.0);
});

it('requires a valid cancellation reason', function () {
    $user = posUser();
    $item = kitchenOrder($user, [[200, 1]])->items->first();

    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $item), [])->assertStatus(422)->assertJsonValidationErrors('reason');
    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $item), ['reason' => 'bored'])->assertJsonValidationErrors('reason');
    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $item), ['reason' => 'other'])->assertJsonValidationErrors('reason_detail');

    // The service enforces it too (for the future API).
    expect(fn () => app(OrderService::class)->cancelItem($item, '   ', $user))->toThrow(PosException::class);

    expect($item->fresh()->kitchen_status)->toBe(OrderItemStatusEnum::PENDING);
});

it('requires the kitchen-cancel permission', function () {
    $user = posUser();
    $item = kitchenOrder($user, [[200, 1]])->items->first();

    $this->actingAs(adminUser(['kitchen-view', 'kitchen-update']))
        ->patchJson(route('pos-kitchen.cancel', $item), ['reason' => 'out_of_stock'])->assertForbidden();
});

it('sends kitchen cancellations to the ready-to-serve feed, incrementally and on first load', function () {
    $user = posUser();
    $order = kitchenOrder($user, [[500, 1], [300, 1]]);
    $item = $order->items->first();
    $cook = cook();

    $this->travel(10)->seconds();
    $first = $this->actingAs($user)->getJson(route('pos-serving.feed'))->json();
    expect($first['items'])->toBeEmpty();

    $this->travel(10)->seconds();
    $this->actingAs($cook)->patchJson(route('pos-kitchen.cancel', $item), ['reason' => 'ingredient_unavailable'])->assertOk();

    $update = $this->actingAs($user)->getJson(route('pos-serving.feed', ['since' => $first['server_time']]))->json();
    $row = collect($update['items'])->firstWhere('id', $item->id);
    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe('cancelled')
        ->and($row['order_active'])->toBeTrue()
        ->and($row['cancellation_reason'])->toBe('Kitchen: Ingredient unavailable')
        ->and($row['cancelled_by'])->toBe($cook->name)
        ->and($row['cancelled_at'])->not->toBeNull();

    // A waiter opening the screen afterwards still sees it...
    $fresh = $this->actingAs($user)->getJson(route('pos-serving.feed'))->json();
    expect(collect($fresh['items'])->pluck('id'))->toContain($item->id);

    // ...but not once it's older than 30 minutes.
    $this->travel(31)->minutes();
    $later = $this->actingAs($user)->getJson(route('pos-serving.feed'))->json();
    expect(collect($later['items'])->pluck('id'))->not->toContain($item->id);
});

it('keeps kitchen cancellations in completed-order history and reports', function () {
    $user = posUser();
    $order = kitchenOrder($user, [[500, 1], [300, 1]]);
    [$cancelled, $kept] = $order->items;
    $cook = cook();
    $service = app(OrderService::class);

    $this->actingAs($cook)->patchJson(route('pos-kitchen.cancel', $cancelled), ['reason' => 'equipment_issue'])->assertOk();
    foreach ([OrderItemStatusEnum::PREPARING, OrderItemStatusEnum::READY, OrderItemStatusEnum::SERVED] as $status) {
        $service->transitionItem($kept->fresh(), $status, $user);
    }
    app(PaymentService::class)->record($order, PaymentMethodEnum::CASH, 300, $user);
    $service->complete($order, $user);

    $this->actingAs($user)->get(route('pos-orders.show', $order))->assertOk()
        ->assertSee('Kitchen: Kitchen equipment issue')->assertSee($cook->name);

    $report = app(PosReportService::class)->build(today(), today());
    expect($report['sales'])->toBe(300.0)
        ->and($report['cancelled_items']->pluck('id'))->toContain($cancelled->id)
        ->and($report['cancelled_items_value'])->toBe(500.0);
});

it('marks a menu item unavailable only as a separate, permission-protected action', function () {
    $user = posUser();
    $category = posSetup();
    $order = kitchenOrder($user, [[500, 1]], $category);
    $item = $order->items->first();
    $menuItem = $item->menuItem;

    // Cancelling never touches availability, even for "out of stock".
    $this->actingAs(cook())->patchJson(route('pos-kitchen.cancel', $item), ['reason' => 'out_of_stock'])
        ->assertOk()->assertJson(['offer_mark_unavailable' => false]);
    expect($menuItem->fresh()->is_available)->toBeTrue();

    // Without menu edit permission the separate action is refused.
    $this->actingAs(cook())->patchJson(route('pos-kitchen.mark-unavailable', $item))->assertForbidden();
    expect($menuItem->fresh()->is_available)->toBeTrue();

    // A cook who may edit menu items is offered it, and can do it explicitly.
    $headCook = adminUser(['kitchen-view', 'kitchen-cancel', 'restaurant_menu_item-edit']);
    $second = kitchenOrder($user, [[500, 1]], $category);
    $secondItem = $second->items->first();
    $secondItem->update(['restaurant_menu_item_id' => $menuItem->id]);

    $this->actingAs($headCook)->patchJson(route('pos-kitchen.cancel', $secondItem), ['reason' => 'out_of_stock'])
        ->assertOk()->assertJson(['offer_mark_unavailable' => true]);
    expect($menuItem->fresh()->is_available)->toBeTrue();

    $this->actingAs($headCook)->patchJson(route('pos-kitchen.mark-unavailable', $secondItem))->assertOk();
    expect($menuItem->fresh()->is_available)->toBeFalse();
});
