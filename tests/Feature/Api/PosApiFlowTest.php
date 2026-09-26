<?php

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = posSetup(vat: 5, serviceCharge: 10);
    $this->ramen = posMenuItem($this->category, 500, ['name' => 'Shoyu Ramen']);
    $this->gyoza = posMenuItem($this->category, 250, ['name' => 'Gyoza']);
    $this->table = posTable('T1');
    $this->user = posUser();
    Sanctum::actingAs($this->user);
});

function apiOpen(array $body = []): TestResponse
{
    return test()->postJson('/api/v1/pos/orders', array_merge(['order_type' => 'dine_in', 'dining_table_id' => test()->table->id, 'guest_count' => 2], $body));
}

function apiRound(int $orderId, array $items, string $key = 'round-1'): TestResponse
{
    return test()->postJson("/api/v1/pos/orders/{$orderId}/rounds", ['submission_key' => $key, 'items' => $items]);
}

function apiServeAll(int $orderId): void
{
    foreach (OrderItem::where('order_id', $orderId)->where('kitchen_status', 'pending')->pluck('id') as $id) {
        test()->postJson("/api/v1/pos/order-items/{$id}/start")->assertOk();
        test()->postJson("/api/v1/pos/order-items/{$id}/ready")->assertOk();
        test()->postJson("/api/v1/pos/order-items/{$id}/served")->assertOk();
    }
}

/* ---------------- Bootstrap / tables / menu ---------------- */

it('returns bootstrap reference data for the app', function () {
    $this->getJson('/api/v1/pos/bootstrap')->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.api_version', 'v1')
        ->assertJsonPath('data.restaurant.vat_percent', '5.00')
        ->assertJsonPath('data.restaurant.service_charge_percent', '10.00')
        ->assertJsonPath('data.tables.0.name', 'T1')
        ->assertJsonPath('data.tables.0.status', 'available')
        ->assertJsonPath('data.categories.0.name', 'Ramen')
        ->assertJsonCount(2, 'data.menu_items')
        ->assertJsonStructure(['data' => [
            'server_time', 'poll_interval_seconds', 'catalog_updated_at', 'user' => ['permissions'],
            'menu_items' => [['id', 'category_id', 'name', 'price', 'is_available', 'image_url']],
            'options' => ['order_types', 'payment_methods', 'discount_types', 'kitchen_cancel_reasons', 'item_statuses'],
        ]])
        // CMS-only fields are not exposed.
        ->assertJsonMissingPath('data.menu_items.0.slug')
        ->assertJsonMissingPath('data.menu_items.0.description');
});

it('lists tables with occupancy derived from the running order', function () {
    $orderId = apiOpen()->json('data.id');

    $this->getJson('/api/v1/pos/tables')->assertOk()
        ->assertJsonPath('data.0.status', 'occupied')
        ->assertJsonPath('data.0.active_order.id', $orderId);

    $this->postJson("/api/v1/pos/orders/{$orderId}/rounds", ['submission_key' => 'k', 'items' => [['menu_item_id' => $this->ramen->id, 'quantity' => 1]]]);
    $this->postJson("/api/v1/pos/orders/{$orderId}/bill-request")->assertOk();
    $this->getJson('/api/v1/pos/tables')->assertJsonPath('data.0.status', 'bill_requested');
});

/* ---------------- Orders / rounds ---------------- */

it('opens a table order and returns the existing one when opened again', function () {
    $first = apiOpen()->assertCreated()->assertJsonPath('created', true)->assertJsonPath('data.table.name', 'T1');
    expect($first->json('data.order_number'))->toStartWith('ORD-');

    apiOpen()->assertOk()->assertJsonPath('created', false)->assertJsonPath('data.id', $first->json('data.id'));
    expect(Order::count())->toBe(1);
});

it('opens takeaway orders without a table', function () {
    $this->postJson('/api/v1/pos/orders', ['order_type' => 'takeaway'])->assertCreated()->assertJsonPath('data.order_type', 'takeaway')->assertJsonPath('data.table', null);
    $this->postJson('/api/v1/pos/orders', ['order_type' => 'takeaway'])->assertCreated();
    $this->postJson('/api/v1/pos/orders', ['order_type' => 'dine_in'])->assertStatus(422)->assertJsonValidationErrors('dining_table_id');

    $this->getJson('/api/v1/pos/orders/active')->assertOk()->assertJsonPath('meta.total', 2);
});

it('adds rounds with server-side totals, notes and snapshots', function () {
    $orderId = apiOpen()->json('data.id');

    apiRound($orderId, [
        ['menu_item_id' => $this->ramen->id, 'quantity' => 2, 'note' => 'no egg', 'price' => 1],
        ['menu_item_id' => $this->ramen->id, 'quantity' => 1, 'note' => 'extra spicy'],
    ])->assertCreated()
        ->assertJsonPath('duplicate', false)
        ->assertJsonPath('round_no', 1)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.note', 'no egg')
        ->assertJsonPath('data.items.1.note', 'extra spicy')
        ->assertJsonPath('data.items.0.unit_price', '500.00')
        // 1500 + 10% service (150) + 5% VAT (75)
        ->assertJsonPath('data.totals.subtotal', '1500.00')
        ->assertJsonPath('data.totals.grand_total', '1725.00');

    apiRound($orderId, [['menu_item_id' => $this->gyoza->id, 'quantity' => 1]], 'round-2')
        ->assertCreated()->assertJsonPath('round_no', 2)->assertJsonPath('data.totals.subtotal', '1750.00');

    // Later price change never touches the placed order.
    $this->ramen->update(['price' => 999, 'name' => 'Renamed']);
    $this->getJson("/api/v1/pos/orders/{$orderId}")->assertJsonPath('data.items.0.name', 'Shoyu Ramen')->assertJsonPath('data.items.0.unit_price', '500.00');
});

it('requires a submission_key and never duplicates a retried round', function () {
    $orderId = apiOpen()->json('data.id');
    $items = [['menu_item_id' => $this->ramen->id, 'quantity' => 1]];

    $this->postJson("/api/v1/pos/orders/{$orderId}/rounds", ['items' => $items])->assertStatus(422)->assertJsonValidationErrors('submission_key');

    apiRound($orderId, $items, 'same')->assertCreated();
    apiRound($orderId, $items, 'same')->assertOk()->assertJsonPath('duplicate', true)->assertJsonCount(1, 'data.items');
    // Even much later (offline retry queue).
    $this->travel(3)->hours();
    apiRound($orderId, $items, 'same')->assertOk()->assertJsonPath('duplicate', true);

    expect(OrderItem::count())->toBe(1);
});

it('rejects unavailable items and changes to closed orders with 422', function () {
    $orderId = apiOpen()->json('data.id');
    $this->gyoza->update(['is_available' => false]);

    apiRound($orderId, [['menu_item_id' => $this->gyoza->id, 'quantity' => 1]])
        ->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('message', 'Gyoza is no longer available. Remove it and send again.');

    $this->postJson("/api/v1/pos/orders/{$orderId}/cancel", ['reason' => 'left'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 1]], 'x')->assertStatus(422);
    expect(DiningTable::first()->isOccupied())->toBeFalse();
});

/* ---------------- Kitchen ---------------- */

it('moves items pending → preparing → ready and rejects invalid transitions', function () {
    $orderId = apiOpen()->json('data.id');
    $itemId = apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 1]])->json('data.items.0.id');

    $feed = $this->getJson('/api/v1/pos/kitchen/feed')->assertOk()->assertJsonPath('data.full', true);
    expect(collect($feed->json('data.items'))->pluck('id')->all())->toBe([$itemId]);

    $this->postJson("/api/v1/pos/order-items/{$itemId}/ready")->assertStatus(422)->assertJsonPath('success', false);
    $this->postJson("/api/v1/pos/order-items/{$itemId}/start")->assertOk()->assertJsonPath('data.status', 'preparing');
    $this->postJson("/api/v1/pos/order-items/{$itemId}/start")->assertStatus(422);
    $this->postJson("/api/v1/pos/order-items/{$itemId}/ready")->assertOk()->assertJsonPath('data.status', 'ready');

    // Incremental feed shows the item left the kitchen.
    $this->travel(5)->seconds();
    $since = $feed->json('data.server_time');
    $changes = $this->getJson('/api/v1/pos/kitchen/feed?since='.urlencode($since))->assertJsonPath('data.full', false);
    expect(collect($changes->json('data.items'))->firstWhere('id', $itemId)['status'])->toBe('ready');
});

it('lets the kitchen cancel one item with a reason, recalculating totals', function () {
    $orderId = apiOpen()->json('data.id');
    $order = apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 1], ['menu_item_id' => $this->gyoza->id, 'quantity' => 1]]);
    [$ramenId, $gyozaId] = [$order->json('data.items.0.id'), $order->json('data.items.1.id')];

    $this->postJson("/api/v1/pos/order-items/{$ramenId}/cancel", [])->assertStatus(422)->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/pos/order-items/{$ramenId}/cancel", ['reason' => 'out_of_stock'])->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Kitchen: Out of stock')
        ->assertJsonPath('data.cancelled_by.id', $this->user->id)
        ->assertJsonPath('offer_mark_unavailable', false);
    $this->postJson("/api/v1/pos/order-items/{$ramenId}/cancel", ['reason' => 'out_of_stock'])->assertStatus(422);

    $this->getJson("/api/v1/pos/orders/{$orderId}")
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.totals.subtotal', '250.00');

    // Served items can't be cancelled.
    foreach (['start', 'ready', 'served'] as $step) {
        $this->postJson("/api/v1/pos/order-items/{$gyozaId}/{$step}")->assertOk();
    }
    $this->postJson("/api/v1/pos/order-items/{$gyozaId}/cancel", ['reason' => 'unable_to_prepare'])->assertStatus(422);
    $this->postJson("/api/v1/pos/order-items/{$gyozaId}/floor-cancel", ['reason' => 'x'])->assertStatus(422);
});

/* ---------------- Serving ---------------- */

it('serves ready items, safely repeats served, and feeds cancellations to waiters', function () {
    $orderId = apiOpen()->json('data.id');
    $r = apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 1], ['menu_item_id' => $this->gyoza->id, 'quantity' => 1]]);
    [$a, $b] = [$r->json('data.items.0.id'), $r->json('data.items.1.id')];

    $this->postJson("/api/v1/pos/order-items/{$a}/start");
    $this->postJson("/api/v1/pos/order-items/{$a}/ready");
    $this->postJson("/api/v1/pos/order-items/{$b}/cancel", ['reason' => 'duplicate_item']);

    $feed = $this->getJson('/api/v1/pos/serving/feed')->assertOk()->json('data.items');
    expect(collect($feed)->firstWhere('id', $a)['status'])->toBe('ready')
        ->and(collect($feed)->firstWhere('id', $b)['cancellation_reason'])->toBe('Kitchen: Duplicate item');

    $this->postJson("/api/v1/pos/order-items/{$a}/acknowledge")->assertOk()->assertJsonPath('data.ready_acknowledged_at', fn ($v) => $v !== null);
    $this->postJson("/api/v1/pos/order-items/{$a}/served")->assertOk()->assertJsonPath('data.status', 'served');
    $this->postJson("/api/v1/pos/order-items/{$a}/served")->assertOk()->assertJsonPath('data.status', 'served');
});

/* ---------------- Billing ---------------- */

it('reports billing totals and completion blockers from the service', function () {
    $orderId = apiOpen()->json('data.id');
    apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 2]]);

    $this->getJson("/api/v1/pos/orders/{$orderId}/billing")->assertOk()
        ->assertJsonPath('data.totals.subtotal', '1000.00')
        ->assertJsonPath('data.totals.service_charge', '100.00')
        ->assertJsonPath('data.totals.vat', '50.00')
        ->assertJsonPath('data.totals.grand_total', '1150.00')
        ->assertJsonPath('data.totals.due', '1150.00')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('completion.allowed', false)
        ->assertJsonPath('completion.outstanding_items', 1);

    $this->postJson("/api/v1/pos/orders/{$orderId}/discount", ['discount_type' => 'percent', 'discount_value' => 10])->assertOk()
        ->assertJsonPath('data.totals.discount', '100.00')
        // (1000 - 100) + 10% + 5%
        ->assertJsonPath('data.totals.grand_total', '1035.00');

    $this->postJson("/api/v1/pos/orders/{$orderId}/complete")->assertStatus(422)->assertJsonPath('success', false);
});

/* ---------------- Payments / completion ---------------- */

it('takes split cash + card payments, auto-completes and releases the table', function () {
    $orderId = apiOpen()->json('data.id');
    apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 2]]); // 1150.00
    apiServeAll($orderId);

    $this->postJson("/api/v1/pos/orders/{$orderId}/payments", ['payment_method' => 'cash', 'amount' => 500])
        ->assertStatus(422)->assertJsonValidationErrors('idempotency_key');

    $this->postJson("/api/v1/pos/orders/{$orderId}/payments", ['payment_method' => 'cash', 'amount' => 500, 'idempotency_key' => 'p1'])
        ->assertCreated()
        ->assertJsonPath('data.payment.method', 'cash')
        ->assertJsonPath('data.order.totals.due', '650.00')
        ->assertJsonPath('data.order.payment_status', 'partial')
        ->assertJsonPath('order_completed', false);

    $this->postJson("/api/v1/pos/orders/{$orderId}/payments", ['payment_method' => 'card', 'amount' => 700, 'idempotency_key' => 'p2'])
        ->assertStatus(422)->assertJsonPath('success', false);

    $this->postJson("/api/v1/pos/orders/{$orderId}/payments", ['payment_method' => 'card', 'amount' => 650, 'idempotency_key' => 'p3', 'reference_no' => '4242'])
        ->assertCreated()
        ->assertJsonPath('order_completed', true)
        ->assertJsonPath('data.order.status', 'completed')
        ->assertJsonPath('data.order.payment_method_summary', 'mixed');

    expect(DiningTable::first()->isOccupied())->toBeFalse();
    $this->getJson('/api/v1/pos/tables')->assertJsonPath('data.0.status', 'available');
});

it('gives cash change and replays duplicate payment keys safely', function () {
    $orderId = apiOpen()->json('data.id');
    apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 2]]); // 1150.00

    $body = ['payment_method' => 'cash', 'amount' => 2000, 'idempotency_key' => 'same-key'];
    $this->postJson("/api/v1/pos/orders/{$orderId}/payments", $body)->assertCreated()
        ->assertJsonPath('data.payment.amount', '1150.00')
        ->assertJsonPath('data.payment.change_amount', '850.00')
        // Food not served yet → paid but not completed.
        ->assertJsonPath('order_completed', false)
        ->assertJsonPath('completion_blocked_reason', '1 item(s) are not served yet. Mark them served or cancel them before completing.');

    $this->postJson("/api/v1/pos/orders/{$orderId}/payments", $body)->assertOk()->assertJsonPath('duplicate', true);
    expect(Payment::count())->toBe(1);

    // The same key on another order is refused.
    $otherId = $this->postJson('/api/v1/pos/orders', ['order_type' => 'takeaway'])->json('data.id');
    apiRound($otherId, [['menu_item_id' => $this->gyoza->id, 'quantity' => 1]]);
    $this->postJson("/api/v1/pos/orders/{$otherId}/payments", $body)->assertStatus(422);
});

it('voids a payment, then completes explicitly once fully paid', function () {
    $orderId = apiOpen()->json('data.id');
    apiRound($orderId, [['menu_item_id' => $this->gyoza->id, 'quantity' => 1]]); // 250 + 25 + 12.5 = 287.50

    $paymentId = $this->postJson("/api/v1/pos/orders/{$orderId}/payments", ['payment_method' => 'card', 'amount' => 100, 'idempotency_key' => 'v1'])->json('data.payment.id');
    $this->postJson("/api/v1/pos/payments/{$paymentId}/void", [])->assertStatus(422)->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/pos/payments/{$paymentId}/void", ['reason' => 'wrong card'])->assertOk()
        ->assertJsonPath('data.payment.status', 'voided')
        ->assertJsonPath('data.order.totals.paid', '0.00');

    $this->getJson("/api/v1/pos/orders/{$orderId}/payments")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.void_reason', 'wrong card');

    apiServeAll($orderId);
    $this->postJson("/api/v1/pos/orders/{$orderId}/complete")->assertStatus(422);

    // A user without order-complete: payment settles the bill but doesn't complete.
    Sanctum::actingAs(adminUser(['payment-create']));
    $this->postJson("/api/v1/pos/orders/{$orderId}/payments", ['payment_method' => 'cash', 'amount' => 287.5, 'idempotency_key' => 'v2'])
        ->assertCreated()->assertJsonPath('order_completed', false);

    Sanctum::actingAs($this->user);
    $this->getJson("/api/v1/pos/orders/{$orderId}/billing")->assertJsonPath('completion.allowed', true);
    $this->postJson("/api/v1/pos/orders/{$orderId}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

    // Completed orders are read-only.
    $this->postJson("/api/v1/pos/orders/{$orderId}/cancel", ['reason' => 'x'])->assertStatus(422);
    $this->postJson("/api/v1/pos/payments/{$paymentId}/void", ['reason' => 'x'])->assertStatus(422);
    apiRound($orderId, [['menu_item_id' => $this->gyoza->id, 'quantity' => 1]], 'late')->assertStatus(422);
});

/* ---------------- History / reports ---------------- */

it('returns completed order history with rounds, timeline, cancellations and payments', function () {
    $orderId = apiOpen()->json('data.id');
    $r = apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 1, 'note' => 'no egg'], ['menu_item_id' => $this->gyoza->id, 'quantity' => 1]]);
    $this->postJson('/api/v1/pos/order-items/'.$r->json('data.items.1.id').'/cancel', ['reason' => 'out_of_stock']);
    apiServeAll($orderId);
    $this->postJson("/api/v1/pos/orders/{$orderId}/payments", ['payment_method' => 'cash', 'amount' => 575, 'idempotency_key' => 'h1'])->assertJsonPath('order_completed', true);

    $this->getJson("/api/v1/pos/orders/{$orderId}/history")->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.items.0.note', 'no egg')
        ->assertJsonPath('data.items.0.round_no', 1)
        ->assertJsonPath('data.items.0.served_by.id', $this->user->id)
        ->assertJsonPath('data.items.1.status', 'cancelled')
        ->assertJsonPath('data.items.1.cancellation_reason', 'Kitchen: Out of stock')
        ->assertJsonPath('data.payments.0.method', 'cash')
        ->assertJsonPath('data.completed_by.id', $this->user->id)
        ->assertJsonPath('data.totals.grand_total', '575.00');

    $this->getJson('/api/v1/pos/orders/completed')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $orderId);
});

it('serves report totals from the report service', function () {
    foreach ([['cash', 575, 'r1'], ['card', 575, 'r2']] as [$method, $amount, $key]) {
        $orderId = $this->postJson('/api/v1/pos/orders', ['order_type' => 'takeaway'])->json('data.id');
        apiRound($orderId, [['menu_item_id' => $this->ramen->id, 'quantity' => 1]], $key);
        apiServeAll($orderId);
        $this->postJson("/api/v1/pos/orders/{$orderId}/payments", ['payment_method' => $method, 'amount' => $amount, 'idempotency_key' => $key]);
    }

    $this->getJson('/api/v1/pos/reports/summary')->assertOk()
        ->assertJsonPath('data.orders_count', 2)
        ->assertJsonPath('data.sales', '1150.00')
        ->assertJsonPath('data.cash_total', '575.00')
        ->assertJsonPath('data.card_total', '575.00')
        ->assertJsonPath('data.average_order', '575.00');

    $this->getJson('/api/v1/pos/reports/sales?date_from='.today()->toDateString().'&date_to='.today()->toDateString())->assertOk()
        ->assertJsonPath('data.by_item.0.name', 'Shoyu Ramen')
        ->assertJsonPath('data.by_item.0.quantity', 2);

    $this->getJson('/api/v1/pos/reports/summary?date_from=2026-02-10&date_to=2026-01-01')->assertStatus(422)->assertJsonValidationErrors('date_to');
});
