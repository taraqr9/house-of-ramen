<?php

use App\Enums\OrderTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Pos\OrderService;
use App\Services\Pos\PaymentService;
use App\Services\Pos\PosReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $category = posSetup();
    $this->menuItem = posMenuItem($category, 300);
    $this->table = posTable();
    $owner = posUser();
    [$this->order] = app(OrderService::class)->open(OrderTypeEnum::DINE_IN, $this->table->id, null, null, $owner);
    app(OrderService::class)->addRound($this->order, [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]], $owner);
    $this->item = $this->order->items()->first();
    $this->payment = app(PaymentService::class)->record($this->order, PaymentMethodEnum::CASH, 100, $owner);
});

it('does not let a valid token without POS permissions do anything', function () {
    Sanctum::actingAs(adminUser(['dashboard-view']));
    $o = $this->order->id;
    $i = $this->item->id;
    $p = $this->payment->id;

    $endpoints = [
        ['getJson', '/api/v1/pos/bootstrap'], ['getJson', '/api/v1/pos/tables'], ['getJson', '/api/v1/pos/menu'],
        ['getJson', '/api/v1/pos/orders/active'], ['getJson', '/api/v1/pos/orders/completed'],
        ['postJson', '/api/v1/pos/orders', ['order_type' => 'takeaway']],
        ['getJson', "/api/v1/pos/orders/{$o}"], ['getJson', "/api/v1/pos/orders/{$o}/history"],
        ['patchJson', "/api/v1/pos/orders/{$o}", ['guest_count' => 3]],
        ['postJson', "/api/v1/pos/orders/{$o}/rounds", ['submission_key' => 'k', 'items' => [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]]]],
        ['postJson', "/api/v1/pos/orders/{$o}/bill-request"], ['getJson', "/api/v1/pos/orders/{$o}/billing"],
        ['postJson', "/api/v1/pos/orders/{$o}/discount", ['discount_type' => 'fixed', 'discount_value' => 10]],
        ['postJson', "/api/v1/pos/orders/{$o}/cancel", ['reason' => 'x']], ['postJson', "/api/v1/pos/orders/{$o}/complete"],
        ['getJson', "/api/v1/pos/orders/{$o}/payments"],
        ['postJson', "/api/v1/pos/orders/{$o}/payments", ['payment_method' => 'cash', 'amount' => 10, 'idempotency_key' => 'x']],
        ['postJson', "/api/v1/pos/payments/{$p}/void", ['reason' => 'x']],
        ['getJson', '/api/v1/pos/kitchen/feed'], ['getJson', '/api/v1/pos/serving/feed'],
        ['postJson', "/api/v1/pos/order-items/{$i}/start"], ['postJson', "/api/v1/pos/order-items/{$i}/ready"],
        ['postJson', "/api/v1/pos/order-items/{$i}/cancel", ['reason' => 'out_of_stock']],
        ['postJson', "/api/v1/pos/order-items/{$i}/floor-cancel", ['reason' => 'x']],
        ['postJson', "/api/v1/pos/order-items/{$i}/mark-menu-unavailable"],
        ['postJson', "/api/v1/pos/order-items/{$i}/acknowledge"], ['postJson', "/api/v1/pos/order-items/{$i}/served"],
        ['getJson', '/api/v1/pos/reports/summary'], ['getJson', '/api/v1/pos/reports/sales'],
    ];

    foreach ($endpoints as $endpoint) {
        [$method, $url] = $endpoint;
        $this->{$method}($url, $endpoint[2] ?? [])
            ->assertForbidden()
            ->assertJson(['success' => false]);
    }

    // Nothing changed.
    expect(Order::count())->toBe(1)
        ->and(Payment::count())->toBe(1)
        ->and($this->item->fresh()->kitchen_status->value)->toBe('pending')
        ->and($this->order->fresh()->status->value)->toBe('open')
        ->and($this->menuItem->fresh()->is_available)->toBeTrue();
});

it('keeps kitchen staff away from money and cashiers away from the kitchen', function () {
    Sanctum::actingAs(adminUser(['kitchen-view', 'kitchen-update', 'kitchen-cancel']));
    $this->postJson("/api/v1/pos/order-items/{$this->item->id}/start")->assertOk();
    $this->postJson("/api/v1/pos/orders/{$this->order->id}/payments", ['payment_method' => 'cash', 'amount' => 10, 'idempotency_key' => 'k1'])->assertForbidden();
    $this->postJson("/api/v1/pos/order-items/{$this->item->id}/served")->assertForbidden();
    $this->getJson("/api/v1/pos/orders/{$this->order->id}/billing")->assertForbidden();

    Sanctum::actingAs(adminUser(['billing-view', 'payment-view', 'payment-create', 'order-complete']));
    $this->postJson("/api/v1/pos/order-items/{$this->item->id}/ready")->assertForbidden();
    $this->postJson("/api/v1/pos/order-items/{$this->item->id}/cancel", ['reason' => 'out_of_stock'])->assertForbidden();
    $this->getJson('/api/v1/pos/kitchen/feed')->assertForbidden();
    $this->getJson("/api/v1/pos/orders/{$this->order->id}/billing")->assertOk();
    $this->postJson("/api/v1/pos/orders/{$this->order->id}/payments", ['payment_method' => 'cash', 'amount' => 10, 'idempotency_key' => 'k2'])->assertCreated();
    $this->postJson("/api/v1/pos/payments/{$this->payment->id}/void", ['reason' => 'x'])->assertForbidden();

    expect($this->item->fresh()->kitchen_status->value)->toBe('preparing');
});

it('lets a waiter with order-create work running orders but not read history or reports', function () {
    Sanctum::actingAs(adminUser(['order-create']));

    $this->getJson('/api/v1/pos/bootstrap')->assertOk();
    $this->getJson("/api/v1/pos/orders/{$this->order->id}")->assertOk();
    $this->postJson("/api/v1/pos/orders/{$this->order->id}/rounds", ['submission_key' => 'w1', 'items' => [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]]])->assertCreated();
    $this->getJson("/api/v1/pos/orders/{$this->order->id}/history")->assertForbidden();
    $this->getJson('/api/v1/pos/orders/completed')->assertForbidden();
    $this->getJson('/api/v1/pos/reports/summary')->assertForbidden();
});

it('requires restaurant_menu_item-edit to mark a dish unavailable from the kitchen', function () {
    Sanctum::actingAs(adminUser(['kitchen-view', 'kitchen-cancel']));
    $this->postJson("/api/v1/pos/order-items/{$this->item->id}/mark-menu-unavailable")->assertForbidden();

    Sanctum::actingAs(adminUser(['kitchen-cancel', 'restaurant_menu_item-edit']));
    $this->postJson("/api/v1/pos/order-items/{$this->item->id}/mark-menu-unavailable")->assertOk()->assertJsonPath('data.is_available', false);
    expect($this->menuItem->fresh()->is_available)->toBeFalse();
});

it('returns 404 for unknown ids instead of leaking anything', function () {
    Sanctum::actingAs(posUser());

    foreach (['/api/v1/pos/orders/999999', '/api/v1/pos/orders/999999/billing', '/api/v1/pos/orders/999999/history'] as $url) {
        $this->getJson($url)->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    }
    $this->postJson('/api/v1/pos/order-items/999999/start')->assertNotFound();
    $this->postJson('/api/v1/pos/payments/999999/void', ['reason' => 'x'])->assertNotFound();
});

it('ignores mass-assignment attempts on order fields', function () {
    Sanctum::actingAs(posUser());

    $response = $this->postJson('/api/v1/pos/orders', [
        'order_type' => 'takeaway', 'status' => 'completed', 'grand_total' => 1, 'paid_total' => 9999, 'order_number' => 'HACK',
    ])->assertCreated();

    expect($response->json('data.status'))->toBe('open')
        ->and($response->json('data.order_number'))->not->toBe('HACK')
        ->and($response->json('data.totals.paid'))->toBe('0.00');
});

it('hides internal error details behind a generic 500', function () {
    Sanctum::actingAs(posUser());
    $this->mock(PosReportService::class)
        ->shouldReceive('build')->andThrow(new RuntimeException('SQLSTATE[42S02] secret table detail'));

    $response = $this->getJson('/api/v1/pos/reports/summary')->assertStatus(500)
        ->assertExactJson(['success' => false, 'message' => 'Something went wrong.']);

    expect($response->getContent())->not->toContain('SQLSTATE')->not->toContain('trace');
});
