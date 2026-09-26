<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PosCatalogController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ServingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS REST API (v1) - see docs/POS_API.md
|--------------------------------------------------------------------------
| Sanctum Bearer tokens. Every POS action is authorized with the same
| permissions/policies as the web POS, and all state changes go through
| App\Services\Pos\* - controllers only validate, authorize and format.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::middleware('api.active')->group(function () {
            Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

            Route::prefix('pos')->name('pos.')->group(function () {
                Route::get('bootstrap', [PosCatalogController::class, 'bootstrap'])->name('bootstrap');
                Route::get('tables', [PosCatalogController::class, 'tables'])->name('tables');
                Route::get('menu', [PosCatalogController::class, 'menu'])->name('menu');

                // Orders / terminal / billing
                Route::get('orders/active', [OrderController::class, 'active'])->name('orders.active');
                Route::get('orders/completed', [OrderController::class, 'completed'])->name('orders.completed');
                Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
                Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
                Route::patch('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
                Route::get('orders/{order}/history', [OrderController::class, 'history'])->name('orders.history');
                Route::post('orders/{order}/rounds', [OrderController::class, 'addRound'])->name('orders.rounds');
                Route::post('orders/{order}/bill-request', [OrderController::class, 'requestBill'])->name('orders.bill-request');
                Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
                Route::get('orders/{order}/billing', [OrderController::class, 'billing'])->name('orders.billing');
                Route::post('orders/{order}/discount', [OrderController::class, 'discount'])->name('orders.discount');
                Route::post('orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');

                // Payments
                Route::get('orders/{order}/payments', [PaymentController::class, 'index'])->name('payments.index');
                Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('payments.store');
                Route::post('payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

                // Kitchen
                Route::get('kitchen/feed', [KitchenController::class, 'feed'])->name('kitchen.feed');
                Route::post('order-items/{orderItem}/start', [KitchenController::class, 'start'])->name('items.start');
                Route::post('order-items/{orderItem}/ready', [KitchenController::class, 'ready'])->name('items.ready');
                Route::post('order-items/{orderItem}/cancel', [KitchenController::class, 'cancel'])->name('items.cancel');
                Route::post('order-items/{orderItem}/mark-menu-unavailable', [KitchenController::class, 'markMenuUnavailable'])->name('items.mark-menu-unavailable');

                // Ready to Serve / waiter
                Route::get('serving/feed', [ServingController::class, 'feed'])->name('serving.feed');
                Route::post('order-items/{orderItem}/acknowledge', [ServingController::class, 'acknowledge'])->name('items.acknowledge');
                Route::post('order-items/{orderItem}/served', [ServingController::class, 'served'])->name('items.served');

                // Floor-staff item cancellation (order_item-cancel)
                Route::post('order-items/{orderItem}/floor-cancel', [OrderController::class, 'cancelItem'])->name('items.floor-cancel');

                // Reports
                Route::get('reports/summary', [ReportController::class, 'summary'])->name('reports.summary');
                Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
            });
        });
    });
});
