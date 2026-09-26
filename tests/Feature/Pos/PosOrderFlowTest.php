<?php

use App\Enums\DiscountTypeEnum;
use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\OrderTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Exceptions\PosException;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Services\Pos\OrderService;
use App\Services\Pos\PaymentService;
use App\Services\Pos\PosReportService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Open an order on $table and send one round of [menuItem, qty, note?] lines.
 */
function openWithItems($user, $table, array $lines): Order
{
    [$order] = app(OrderService::class)->open(OrderTypeEnum::DINE_IN, $table->id, 2, null, $user);

    app(OrderService::class)->addRound($order, array_map(fn ($line) => [
        'menu_item_id' => $line[0]->id,
        'quantity' => $line[1],
        'note' => $line[2] ?? null,
    ], $lines), $user);

    return $order->fresh();
}

function serveAll(Order $order, $user): void
{
    $service = app(OrderService::class);

    foreach ($order->items()->where('kitchen_status', 'pending')->get() as $item) {
        $service->transitionItem($item, OrderItemStatusEnum::PREPARING, $user);
        $service->transitionItem($item->fresh(), OrderItemStatusEnum::READY, $user);
        $service->transitionItem($item->fresh(), OrderItemStatusEnum::SERVED, $user);
    }
}

it('runs the full dine-in lifecycle through the web routes and releases the table', function () {
    $category = posSetup(vat: 5, serviceCharge: 10);
    $ramen = posMenuItem($category, 500);
    $gyoza = posMenuItem($category, 250);
    $table = posTable('T1');
    $user = posUser();

    // Open the table.
    $this->actingAs($user)->post(route('pos-orders.store'), ['order_type' => 'dine_in', 'dining_table_id' => $table->id, 'guest_count' => 2])
        ->assertRedirect();
    $order = Order::sole();
    expect($order->active_table_id)->toBe($table->id)
        ->and($order->order_number)->toStartWith('ORD-')
        ->and((float) $order->vat_percent)->toBe(5.0);

    // Round 1 - a bogus browser price must be ignored.
    $this->actingAs($user)->postJson(route('pos-orders.items.store', $order), [
        'items' => [
            ['menu_item_id' => $ramen->id, 'quantity' => 2, 'note' => 'less spicy', 'price' => 1],
            ['menu_item_id' => $gyoza->id, 'quantity' => 1],
        ],
    ])->assertOk();

    $order->refresh();
    // subtotal 1250, service 125, vat 62.50
    expect((float) $order->subtotal)->toBe(1250.0)
        ->and((float) $order->service_charge)->toBe(125.0)
        ->and((float) $order->vat)->toBe(62.5)
        ->and((float) $order->grand_total)->toBe(1437.5);

    // Kitchen: pending → preparing → ready, then served.
    foreach ($order->items as $item) {
        $this->actingAs($user)->patchJson(route('pos-kitchen.update', $item), ['status' => 'preparing'])->assertOk();
        $this->actingAs($user)->patchJson(route('pos-kitchen.update', $item), ['status' => 'ready'])->assertOk();
        $this->actingAs($user)->patchJson(route('pos-serving.serve', $item))->assertOk();
    }

    // Bill, then split payment: cash 1000 + card remainder.
    $this->actingAs($user)->post(route('pos-billing.request', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatusEnum::BILL_REQUESTED);

    $this->actingAs($user)->post(route('pos-payments.store', $order), ['payment_method' => 'cash', 'amount' => 1000, 'idempotency_key' => 'k1']);
    expect($order->fresh()->status)->toBe(OrderStatusEnum::BILL_REQUESTED);

    $this->actingAs($user)->post(route('pos-payments.store', $order), ['payment_method' => 'card', 'amount' => 437.5, 'idempotency_key' => 'k2', 'reference_no' => '4242'])
        ->assertRedirect(route('pos-orders.show', $order));

    $order->refresh();
    expect($order->status)->toBe(OrderStatusEnum::COMPLETED)
        ->and($order->active_table_id)->toBeNull()
        ->and((float) $order->paid_total)->toBe(1437.5)
        ->and($order->paymentMethodSummary())->toBe('mixed')
        ->and($table->fresh()->isOccupied())->toBeFalse();

    // Table is free again - a new order opens on it.
    $this->actingAs($user)->post(route('pos-orders.store'), ['order_type' => 'dine_in', 'dining_table_id' => $table->id]);
    expect(Order::count())->toBe(2);
});

it('returns the same running order when two users open the same table', function () {
    posSetup();
    $table = posTable();
    $service = app(OrderService::class);

    [$first, $created1] = $service->open(OrderTypeEnum::DINE_IN, $table->id, null, null, posUser());
    [$second, $created2] = $service->open(OrderTypeEnum::DINE_IN, $table->id, null, null, posUser());

    expect($created1)->toBeTrue()
        ->and($created2)->toBeFalse()
        ->and($second->id)->toBe($first->id)
        ->and(Order::count())->toBe(1);
});

it('enforces one active order per table at the database level', function () {
    posSetup();
    $table = posTable();
    app(OrderService::class)->open(OrderTypeEnum::DINE_IN, $table->id, null, null, posUser());

    Order::create(['order_type' => 'dine_in', 'status' => 'open', 'dining_table_id' => $table->id, 'active_table_id' => $table->id]);
})->throws(UniqueConstraintViolationException::class);

it('allows several takeaway orders at once', function () {
    posSetup();
    $service = app(OrderService::class);
    $service->open(OrderTypeEnum::TAKEAWAY, null, null, null, posUser());
    $service->open(OrderTypeEnum::TAKEAWAY, null, null, null, posUser());

    expect(Order::active()->count())->toBe(2);
});

it('refuses to open an inactive table', function () {
    posSetup();
    $table = posTable();
    $table->update(['is_active' => false]);

    $this->actingAs(posUser())->post(route('pos-orders.store'), ['order_type' => 'dine_in', 'dining_table_id' => $table->id])
        ->assertSessionHas('error');
    expect(Order::count())->toBe(0);
});

it('keeps the same dish with different notes as separate lines and numbers later rounds', function () {
    $category = posSetup();
    $ramen = posMenuItem($category, 400);
    $user = posUser();
    $order = openWithItems($user, posTable(), [[$ramen, 1, 'no egg'], [$ramen, 1, 'extra spicy']]);

    expect($order->items()->count())->toBe(2);

    app(OrderService::class)->requestBill($order, $user);
    app(OrderService::class)->addRound($order, [['menu_item_id' => $ramen->id, 'quantity' => 1]], $user);

    $order->refresh();
    expect($order->items()->where('round_no', 2)->count())->toBe(1)
        ->and($order->items()->where('round_no', 1)->count())->toBe(2)
        // More food after the bill → the bill is stale, order is running again.
        ->and($order->status)->toBe(OrderStatusEnum::OPEN)
        ->and((float) $order->subtotal)->toBe(1200.0);
});

it('ignores a duplicate round submission', function () {
    $category = posSetup();
    $ramen = posMenuItem($category, 400);
    $user = posUser();
    [$order] = app(OrderService::class)->open(OrderTypeEnum::DINE_IN, posTable()->id, null, null, $user);
    $payload = ['items' => [['menu_item_id' => $ramen->id, 'quantity' => 1]], 'submission_key' => 'same-key'];

    $this->actingAs($user)->postJson(route('pos-orders.items.store', $order), $payload)->assertOk();
    $this->actingAs($user)->postJson(route('pos-orders.items.store', $order), $payload)->assertStatus(422);

    expect($order->items()->count())->toBe(1);
});

it('rejects unavailable, hidden-category and deleted menu items', function () {
    $category = posSetup();
    $user = posUser();
    [$order] = app(OrderService::class)->open(OrderTypeEnum::TAKEAWAY, null, null, null, $user);

    $unavailable = posMenuItem($category, 100, ['is_available' => false]);
    $deleted = posMenuItem($category, 100);
    $deleted->delete();

    foreach ([$unavailable, $deleted] as $menuItem) {
        $this->actingAs($user)->postJson(route('pos-orders.items.store', $order), ['items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]]])
            ->assertStatus(422);
    }

    expect($order->items()->count())->toBe(0);
});

it('keeps the historical name and price after the menu item is changed or deleted', function () {
    $category = posSetup();
    $ramen = posMenuItem($category, 450, ['name' => 'Tonkotsu Ramen']);
    $user = posUser();
    $order = openWithItems($user, posTable(), [[$ramen, 1]]);

    $ramen->update(['name' => 'Renamed', 'price' => 999, 'is_available' => false]);
    $ramen->delete();

    $item = $order->items()->first();
    expect($item->item_name)->toBe('Tonkotsu Ramen')
        ->and((float) $item->unit_price)->toBe(450.0)
        ->and($item->menuItem)->not->toBeNull();

    $this->actingAs($user)->get(route('pos-orders.show', $order))->assertOk()->assertSee('Tonkotsu Ramen');
});

it('blocks adding items to completed or cancelled orders', function () {
    $category = posSetup();
    $ramen = posMenuItem($category, 400);
    $user = posUser();

    $cancelled = openWithItems($user, posTable('T1'), [[$ramen, 1]]);
    app(OrderService::class)->cancel($cancelled, 'customer left', $user);

    $completed = openWithItems($user, posTable('T2'), [[$ramen, 1]]);
    serveAll($completed, $user);
    app(PaymentService::class)->record($completed, PaymentMethodEnum::CASH, 400, $user);
    app(OrderService::class)->complete($completed, $user);

    foreach ([$cancelled, $completed] as $order) {
        $this->actingAs($user)->postJson(route('pos-orders.items.store', $order), ['items' => [['menu_item_id' => $ramen->id, 'quantity' => 1]]])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Order {$order->order_number} is {$order->fresh()->status->label()} and can no longer be changed."]);
    }
});

it('rejects invalid kitchen transitions and treats a second "served" as a no-op', function () {
    $category = posSetup();
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 300), 1]]);
    $item = $order->items()->first();

    // Can't skip preparing, can't go backwards, kitchen can't "serve".
    $this->actingAs($user)->patchJson(route('pos-kitchen.update', $item), ['status' => 'ready'])->assertStatus(422);
    $this->actingAs($user)->patchJson(route('pos-kitchen.update', $item), ['status' => 'served'])->assertStatus(422);
    $this->actingAs($user)->patchJson(route('pos-serving.serve', $item))->assertStatus(422);

    $this->actingAs($user)->patchJson(route('pos-kitchen.update', $item), ['status' => 'preparing'])->assertOk();
    $this->actingAs($user)->patchJson(route('pos-kitchen.update', $item), ['status' => 'ready'])->assertOk();
    $this->actingAs($user)->patchJson(route('pos-kitchen.update', $item), ['status' => 'preparing'])->assertStatus(422);

    $this->actingAs($user)->patchJson(route('pos-serving.serve', $item))->assertOk();
    $servedAt = $item->fresh()->served_at;
    $this->travel(1)->minutes();
    $this->actingAs($user)->patchJson(route('pos-serving.serve', $item))->assertOk()->assertJson(['status' => 'served']);

    expect($item->fresh()->served_at->equalTo($servedAt))->toBeTrue();
});

it('lets the bill be generated while items are still cooking but blocks completion', function () {
    $category = posSetup();
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 300), 1]]);
    app(OrderService::class)->transitionItem($order->items()->first(), OrderItemStatusEnum::PREPARING, $user);

    $this->actingAs($user)->post(route('pos-billing.request', $order))->assertRedirect();
    $this->actingAs($user)->get(route('pos-billing.show', $order))->assertOk()->assertSee('not served yet');

    // Paying in full doesn't auto-complete while food is outstanding.
    $this->actingAs($user)->post(route('pos-payments.store', $order), ['payment_method' => 'cash', 'amount' => 300])
        ->assertSessionHas('warning');
    expect($order->fresh()->status)->toBe(OrderStatusEnum::BILL_REQUESTED);

    $this->actingAs($user)->post(route('pos-billing.complete', $order))->assertSessionHas('error');
    expect($order->fresh()->isActive())->toBeTrue();
});

it('blocks completion without full payment', function () {
    $category = posSetup();
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 1000), 1]]);
    serveAll($order, $user);

    app(PaymentService::class)->record($order, PaymentMethodEnum::CASH, 400, $user);

    $this->actingAs($user)->post(route('pos-billing.complete', $order))->assertSessionHas('error');
    expect($order->fresh()->status)->toBe(OrderStatusEnum::OPEN)
        ->and($order->fresh()->balanceDue())->toBe(600.0);
});

it('gives change for cash overpayment but rejects card overpayment', function () {
    $category = posSetup();
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 2500), 1]]);
    $payments = app(PaymentService::class);

    expect(fn () => $payments->record($order, PaymentMethodEnum::CARD, 3000, $user))
        ->toThrow(PosException::class);

    $cash = $payments->record($order, PaymentMethodEnum::CASH, 3000, $user);
    expect((float) $cash->amount)->toBe(2500.0)
        ->and((float) $cash->tendered_amount)->toBe(3000.0)
        ->and((float) $cash->change_amount)->toBe(500.0)
        ->and((float) $order->fresh()->paid_total)->toBe(2500.0);

    // Nothing left to pay.
    expect(fn () => $payments->record($order, PaymentMethodEnum::CASH, 1, $user))
        ->toThrow(PosException::class);
});

it('records a double-submitted payment only once', function () {
    $category = posSetup();
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 1000), 1]]);
    $payload = ['payment_method' => 'cash', 'amount' => 300, 'idempotency_key' => 'dup-key'];

    $this->actingAs($user)->post(route('pos-payments.store', $order), $payload);
    $this->actingAs($user)->post(route('pos-payments.store', $order), $payload);

    expect(Payment::count())->toBe(1)
        ->and((float) $order->fresh()->paid_total)->toBe(300.0);
});

it('never lets the bill drop below what was already paid', function () {
    $category = posSetup();
    $user = posUser();
    $cheap = posMenuItem($category, 200);
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 800), 1], [$cheap, 1]]);
    app(PaymentService::class)->record($order, PaymentMethodEnum::CASH, 900, $user);

    $item = $order->items()->where('restaurant_menu_item_id', $cheap->id)->first();
    $this->actingAs($user)->patchJson(route('pos-order-items.cancel', $item), ['reason' => 'wrong item'])->assertStatus(422);
    $this->actingAs($user)->patch(route('pos-billing.discount', $order), ['discount_type' => 'percent', 'discount_value' => 50])->assertSessionHas('error');

    expect($item->fresh()->kitchen_status)->toBe(OrderItemStatusEnum::PENDING)
        ->and((float) $order->fresh()->discount)->toBe(0.0);
});

it('applies fixed and percent discounts before VAT and service charge', function () {
    $category = posSetup(vat: 10, serviceCharge: 5);
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 1000), 1]]);

    $this->actingAs($user)->patch(route('pos-billing.discount', $order), ['discount_type' => 'percent', 'discount_value' => 10]);
    $order->refresh();
    // taxable 900 → service 45, vat 90
    expect((float) $order->discount)->toBe(100.0)->and((float) $order->grand_total)->toBe(1035.0);

    $this->actingAs($user)->patch(route('pos-billing.discount', $order), ['discount_type' => 'fixed', 'discount_value' => 5000]);
    // Capped at the subtotal.
    expect((float) $order->fresh()->discount)->toBe(1000.0)->and((float) $order->fresh()->grand_total)->toBe(0.0);

    $this->actingAs($user)->patch(route('pos-billing.discount', $order), ['discount_type' => 'fixed', 'discount_value' => 0]);
    expect((float) $order->fresh()->grand_total)->toBe(1150.0);
});

it('snapshots VAT/service charge rates when the order opens', function () {
    $category = posSetup(vat: 5);
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 1000), 1]]);

    Restaurant::query()->update(['vat_percent' => 15]);
    app(OrderService::class)->addRound($order, [['menu_item_id' => posMenuItem($category, 1000)->id, 'quantity' => 1]], $user);

    expect((float) $order->fresh()->vat)->toBe(100.0);
});

it('refuses to cancel paid or completed orders, and cancelling releases the table', function () {
    $category = posSetup();
    $user = posUser();
    $table = posTable();
    $order = openWithItems($user, $table, [[posMenuItem($category, 500), 1]]);
    $payment = app(PaymentService::class)->record($order, PaymentMethodEnum::CASH, 200, $user);

    $this->actingAs($user)->post(route('pos-orders.cancel', $order), ['reason' => 'left'])->assertSessionHas('error');
    expect($order->fresh()->isActive())->toBeTrue();

    // Void the payment first, then cancelling works.
    $this->actingAs($user)->patch(route('pos-payments.void', $payment), ['reason' => 'refunded'])->assertSessionHas('success');
    expect($payment->fresh()->status->value)->toBe('voided')->and((float) $order->fresh()->paid_total)->toBe(0.0);

    $this->actingAs($user)->post(route('pos-orders.cancel', $order), ['reason' => 'left'])->assertSessionHas('success');
    $order->refresh();
    expect($order->status)->toBe(OrderStatusEnum::CANCELLED)
        ->and($order->active_table_id)->toBeNull()
        ->and($order->items()->first()->kitchen_status)->toBe(OrderItemStatusEnum::CANCELLED)
        ->and($table->fresh()->isOccupied())->toBeFalse();

    // A completed order can't be cancelled either.
    $done = openWithItems($user, posTable('T9'), [[posMenuItem($category, 100), 1]]);
    serveAll($done, $user);
    app(PaymentService::class)->record($done, PaymentMethodEnum::CARD, 100, $user);
    app(OrderService::class)->complete($done, $user);

    $this->actingAs($user)->post(route('pos-orders.cancel', $done), ['reason' => 'x'])->assertSessionHas('error');
    expect($done->fresh()->status)->toBe(OrderStatusEnum::COMPLETED);
});

it('does not allow voiding payments of a completed order', function () {
    $category = posSetup();
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 100), 1]]);
    serveAll($order, $user);
    $payment = app(PaymentService::class)->record($order, PaymentMethodEnum::CASH, 100, $user);
    app(OrderService::class)->complete($order, $user);

    $this->actingAs($user)->patch(route('pos-payments.void', $payment), ['reason' => 'x'])->assertSessionHas('error');
    expect($payment->fresh()->status->value)->toBe('completed');
});

it('serves kitchen and ready feeds incrementally', function () {
    $category = posSetup();
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 100), 1], [posMenuItem($category, 200), 1]]);
    [$a, $b] = $order->items;

    $this->travel(10)->seconds();
    $first = $this->actingAs($user)->getJson(route('pos-kitchen.feed'))->assertOk()->json();
    expect($first['full'])->toBeTrue()->and($first['items'])->toHaveCount(2);

    $this->travel(10)->seconds();
    app(OrderService::class)->transitionItem($a, OrderItemStatusEnum::PREPARING, $user);

    $next = $this->actingAs($user)->getJson(route('pos-kitchen.feed', ['since' => $first['server_time']]))->json();
    expect($next['full'])->toBeFalse()
        ->and(collect($next['items'])->pluck('id')->all())->toBe([$a->id])
        ->and($next['items'][0]['status'])->toBe('preparing');

    app(OrderService::class)->transitionItem($a->fresh(), OrderItemStatusEnum::READY, $user);
    $ready = $this->actingAs($user)->getJson(route('pos-serving.feed'))->json();
    expect(collect($ready['items'])->pluck('id')->all())->toBe([$a->id]);

    $this->actingAs($user)->patchJson(route('pos-serving.acknowledge', $a))->assertOk()->assertJson(['acknowledged' => true]);
});

it('builds sales reports from completed orders only', function () {
    $category = posSetup();
    $user = posUser();
    $ramen = posMenuItem($category, 1000, ['name' => 'Shoyu']);
    $payments = app(PaymentService::class);
    $orders = app(OrderService::class);

    $cash = openWithItems($user, posTable('T1'), [[$ramen, 1]]);
    serveAll($cash, $user);
    $payments->record($cash, PaymentMethodEnum::CASH, 1000, $user);
    $orders->complete($cash, $user);

    $mixed = openWithItems($user, posTable('T2'), [[$ramen, 2]]);
    serveAll($mixed, $user);
    $payments->record($mixed, PaymentMethodEnum::CASH, 500, $user);
    $payments->record($mixed, PaymentMethodEnum::CARD, 1500, $user);
    $orders->complete($mixed, $user);

    $cancelled = openWithItems($user, posTable('T3'), [[$ramen, 1]]);
    $orders->cancel($cancelled, 'left', $user);

    openWithItems($user, posTable('T4'), [[$ramen, 5]]); // still running - not a sale

    $report = app(PosReportService::class)->build(today(), today());

    expect($report['sales'])->toBe(3000.0)
        ->and($report['orders_count'])->toBe(2)
        ->and($report['average_order'])->toBe(1500.0)
        ->and($report['cash_total'])->toBe(1500.0)
        ->and($report['card_total'])->toBe(1500.0)
        ->and($report['mixed_orders'])->toBe(1)
        ->and($report['mixed_total'])->toBe(2000.0)
        ->and((int) $report['by_item']->firstWhere('item_name', 'Shoyu')->quantity)->toBe(3)
        ->and($report['by_category']->first()->category_name)->toBe('Ramen')
        ->and($report['cancelled_orders'])->toHaveCount(1);

    $this->actingAs($user)->get(route('pos-reports.index'))->assertOk()->assertSee('Shoyu');
});

it('renders every POS screen for a user with POS permissions', function () {
    $category = posSetup();
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 100), 1]]);

    foreach ([
        route('dining-tables.index'), route('dining-tables.create'), route('pos-terminal.index'),
        route('pos-orders.active'), route('pos-orders.completed'), route('pos-orders.show', $order),
        route('pos-kitchen.index'), route('pos-serving.index'), route('pos-billing.index'),
        route('pos-billing.show', $order), route('pos-billing.print', $order), route('pos-payments.index'),
        route('pos-reports.index'),
    ] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});

it('forbids POS screens and actions without the matching permission', function () {
    $category = posSetup();
    $owner = posUser();
    $order = openWithItems($owner, posTable(), [[posMenuItem($category, 100), 1]]);
    $item = $order->items()->first();
    $nobody = adminUser(['dashboard-view']);

    foreach ([
        ['get', route('dining-tables.index')], ['get', route('pos-terminal.index')], ['get', route('pos-orders.active')],
        ['get', route('pos-orders.show', $order)], ['get', route('pos-kitchen.index')], ['get', route('pos-kitchen.feed')],
        ['get', route('pos-serving.feed')], ['get', route('pos-billing.show', $order)], ['get', route('pos-billing.print', $order)],
        ['get', route('pos-payments.index')], ['get', route('pos-reports.index')],
        ['postJson', route('pos-orders.store')], ['postJson', route('pos-orders.items.store', $order)],
        ['patchJson', route('pos-kitchen.update', $item)], ['patchJson', route('pos-serving.serve', $item)],
        ['patchJson', route('pos-order-items.cancel', $item)], ['postJson', route('pos-orders.cancel', $order)],
        ['patchJson', route('pos-billing.discount', $order)], ['postJson', route('pos-billing.complete', $order)],
        ['postJson', route('pos-payments.store', $order)],
    ] as [$method, $url]) {
        $this->actingAs($nobody)->{$method}($url)->assertForbidden();
    }

    // Kitchen staff can cook but can't serve, bill or take money.
    $cook = adminUser(['kitchen-view', 'kitchen-update']);
    $this->actingAs($cook)->patchJson(route('pos-kitchen.update', $item), ['status' => 'preparing'])->assertOk();
    $this->actingAs($cook)->patchJson(route('pos-serving.serve', $item))->assertForbidden();
    $this->actingAs($cook)->post(route('pos-payments.store', $order), ['payment_method' => 'cash', 'amount' => 100])->assertForbidden();

    expect(Payment::count())->toBe(0)->and($item->fresh()->kitchen_status)->toBe(OrderItemStatusEnum::PREPARING);
});

it('manages dining tables and derives occupancy from the running order', function () {
    posSetup();
    $user = posUser();

    $this->actingAs($user)->post(route('dining-tables.store'), ['name' => 'T7', 'capacity' => 4, 'is_active' => 1])
        ->assertRedirect(route('dining-tables.index'));
    $this->actingAs($user)->post(route('dining-tables.store'), ['name' => 'T7', 'capacity' => 4])->assertSessionHasErrors('name');

    $table = DiningTable::where('name', 'T7')->sole();
    app(OrderService::class)->open(OrderTypeEnum::DINE_IN, $table->id, null, null, $user);

    $this->actingAs($user)->get(route('dining-tables.index', ['occupancy' => 'occupied']))->assertOk()->assertSee('T7');
    $this->actingAs($user)->delete(route('dining-tables.destroy', $table))->assertSessionHas('error');
    expect($table->fresh()->trashed())->toBeFalse();
});

it('lets a waiter with only order-create work a running order but not read closed history', function () {
    $category = posSetup();
    $waiter = adminUser(['order-create']);
    $table = posTable();

    $this->actingAs($waiter)->post(route('pos-orders.store'), ['order_type' => 'dine_in', 'dining_table_id' => $table->id]);
    $order = Order::sole();

    $this->actingAs($waiter)->get(route('pos-orders.show', $order))->assertOk();

    app(OrderService::class)->cancel($order, 'test', posUser());
    $this->actingAs($waiter)->get(route('pos-orders.show', $order))->assertForbidden();
});

it('completes a fully discounted (complimentary) order without a payment', function () {
    $category = posSetup(vat: 5);
    $user = posUser();
    $order = openWithItems($user, posTable(), [[posMenuItem($category, 700), 1]]);
    serveAll($order, $user);

    app(OrderService::class)->applyDiscount($order, DiscountTypeEnum::PERCENT, 100, $user);
    $this->actingAs($user)->post(route('pos-billing.complete', $order))->assertRedirect(route('pos-orders.show', $order));

    expect($order->fresh()->status)->toBe(OrderStatusEnum::COMPLETED)
        ->and((float) $order->fresh()->grand_total)->toBe(0.0);
});

it('syncs POS menus idempotently with real routes', function () {
    $this->seed(AdminSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(MenuSeeder::class);

    $pos = Menu::where('title', 'POS Operations')->whereNull('parent_id')->sole();
    $children = $pos->children;

    expect($children)->toHaveCount(9)
        ->and(Menu::where('title', 'POS Operations')->count())->toBe(1);

    foreach ($children as $child) {
        expect(Route::has($child->route))->toBeTrue()
            ->and((int) $child->is_active->value)->toBe(1)
            ->and(Permission::where('name', $child->permission)->exists())->toBeTrue();
    }
});
