<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PosBillingController;
use App\Http\Controllers\PosOrderController;
use App\Http\Controllers\PosReportController;
use App\Http\Controllers\PosTerminalController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MenuController as PublicMenuController;
use App\Http\Controllers\Public\ReservationController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\RestaurantGalleryImageController;
use App\Http\Controllers\RestaurantMenuCategoryController;
use App\Http\Controllers\RestaurantMenuItemController;
use App\Http\Controllers\RestaurantPopupOfferController;
use App\Http\Controllers\RestaurantReservationController;
use App\Http\Controllers\RestaurantReviewController;
use App\Http\Controllers\RestaurantVideoFeatureController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Crawler-facing infrastructure - deliberately dynamic (not static files
 * in public/) so the Sitemap directive and any future per-environment
 * Disallow rules can use the real config('seo.base_url') instead of a
 * hardcoded domain. See App\Http\Controllers\Public\RobotsController and
 * SitemapController.
 */
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

/*
 * Public House of Ramen website - consumer-facing, no auth, completely
 * separate visually from the admin panel (see resources/js/Pages/Public
 * and Layouts/PublicLayout.vue). Route names are prefixed "public." so
 * they never collide with the admin's resource route names below.
 */
Route::name('public.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/menu', [PublicMenuController::class, 'index'])->name('menu');
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/contact', [ContactController::class, 'index'])->name('contact');

    // Throttled since it's a fully public, unauthenticated endpoint that
    // also triggers an outbound Telegram call (see ReservationController) -
    // caps abuse without needing a captcha.
    Route::post('/reservations', [ReservationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('reservations.store');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginView'])->name('login.view');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotForm'])
        ->name('password.request');

    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
        ->name('password.email');

    Route::get('/reset-password', [ForgotPasswordController::class, 'showResetForm'])
        ->name('password.reset.show');

    Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])
        ->name('password.reset.update');

    Route::get('password/setup', [PasswordSetupController::class, 'show'])
        ->name('password.setup.show');

    Route::post('password/setup', [PasswordSetupController::class, 'update'])
        ->name('password.setup.update');
});

Route::middleware(['auth'])->group(function () {
    Route::get('change-password', [PasswordChangeController::class, 'show'])
        ->name('password.change.show');

    Route::post('change-password', [PasswordChangeController::class, 'update'])
        ->name('password.change.update');

    Route::get('profile', [UserController::class, 'profile'])
        ->name('profile.show');

    Route::put('profile', [UserController::class, 'updateProfile'])
        ->name('profile.update');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');
});

Route::middleware(['auth', 'force.password.change'])->group(function () {
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])
        ->name('users.impersonate');

    Route::post('/impersonate/leave', [UserController::class, 'leaveImpersonate'])
        ->name('users.impersonate.leave');
});

Route::middleware(['auth', 'force.password.change', 'block.impersonation.actions'])->group(function () {
    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class);
    Route::resource('menus', MenuController::class)->except(['show']);

    Route::get('/logs/activity', [ActivityLogController::class, 'index'])
        ->name('logs.activity');
    Route::get('logs/error', [ErrorLogController::class, 'index'])
        ->name('logs.error');

    Route::post('/permissions/store', [RoleController::class, 'storePermission'])
        ->name('permissions.store');

    // Lives under /admin (not /) so the root path is free for the public
    // House of Ramen site - the route NAME stays "dashboard" so every
    // existing route()/redirect()->route() call and the DB-driven sidebar
    // menu (which stores route names, not raw URLs) keep working unchanged.
    Route::get('/admin', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::post('{notification}/read', [NotificationController::class, 'read'])->name('read');
    });

    Route::prefix('restaurant')->group(function () {
        Route::get('/', [RestaurantController::class, 'edit'])->name('restaurant.edit');
        Route::put('/', [RestaurantController::class, 'update'])->name('restaurant.update');

        Route::resource('menu-categories', RestaurantMenuCategoryController::class)
            ->except(['show'])
            ->names('restaurant-menu-categories')
            ->parameters(['menu-categories' => 'restaurant_menu_category']);

        Route::resource('menu-items', RestaurantMenuItemController::class)
            ->except(['show'])
            ->names('restaurant-menu-items')
            ->parameters(['menu-items' => 'restaurant_menu_item']);

        Route::delete('menu-items/{restaurant_menu_item}/images/{image}', [RestaurantMenuItemController::class, 'destroyImage'])
            ->name('restaurant-menu-items.images.destroy');

        Route::patch('menu-items/{restaurant_menu_item}/toggle-featured', [RestaurantMenuItemController::class, 'toggleFeatured'])
            ->name('restaurant-menu-items.toggle-featured');
        Route::patch('menu-items/{restaurant_menu_item}/toggle-new', [RestaurantMenuItemController::class, 'toggleNew'])
            ->name('restaurant-menu-items.toggle-new');

        Route::resource('gallery', RestaurantGalleryImageController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('restaurant-gallery-images')
            ->parameters(['gallery' => 'restaurant_gallery_image']);

        Route::resource('video-features', RestaurantVideoFeatureController::class)
            ->except(['show'])
            ->names('restaurant-video-features')
            ->parameters(['video-features' => 'restaurant_video_feature']);

        Route::resource('popup-offers', RestaurantPopupOfferController::class)
            ->except(['show'])
            ->names('restaurant-popup-offers')
            ->parameters(['popup-offers' => 'restaurant_popup_offer']);

        Route::patch('popup-offers/{restaurant_popup_offer}/toggle-active', [RestaurantPopupOfferController::class, 'toggleActive'])
            ->name('restaurant-popup-offers.toggle-active');

        Route::resource('reviews', RestaurantReviewController::class)
            ->except(['show'])
            ->names('restaurant-reviews')
            ->parameters(['reviews' => 'restaurant_review']);

        Route::patch('reviews/{restaurant_review}/toggle-active', [RestaurantReviewController::class, 'toggleActive'])
            ->name('restaurant-reviews.toggle-active');

        Route::get('reservations', [RestaurantReservationController::class, 'index'])
            ->name('restaurant-reservations.index');
        Route::patch('reservations/{restaurant_reservation}', [RestaurantReservationController::class, 'update'])
            ->name('restaurant-reservations.update');
        Route::delete('reservations/{restaurant_reservation}', [RestaurantReservationController::class, 'destroy'])
            ->name('restaurant-reservations.destroy');
    });

    /*
     * POS Operations. Screens are thin - every state change goes through
     * App\Services\Pos\OrderService / PaymentService so the future REST
     * API can reuse the exact same rules. Kitchen/Ready-to-Serve "live"
     * updates are plain AJAX polling of the *.feed endpoints (no
     * websockets/Redis - runs on ordinary cPanel hosting).
     */
    Route::prefix('pos')->group(function () {
        Route::resource('tables', DiningTableController::class)
            ->except(['show'])
            ->names('dining-tables')
            ->parameters(['tables' => 'dining_table']);

        Route::get('terminal', [PosTerminalController::class, 'index'])->name('pos-terminal.index');

        Route::get('orders/active', [PosOrderController::class, 'active'])->name('pos-orders.active');
        Route::get('orders/completed', [PosOrderController::class, 'completed'])->name('pos-orders.completed');
        Route::post('orders', [PosTerminalController::class, 'open'])->name('pos-orders.store');
        Route::get('orders/{order}', [PosOrderController::class, 'show'])->name('pos-orders.show');
        Route::patch('orders/{order}', [PosOrderController::class, 'update'])->name('pos-orders.update');
        Route::post('orders/{order}/items', [PosTerminalController::class, 'addItems'])->name('pos-orders.items.store');
        Route::post('orders/{order}/cancel', [PosOrderController::class, 'cancel'])->name('pos-orders.cancel');
        Route::patch('order-items/{order_item}/cancel', [PosOrderController::class, 'cancelItem'])->name('pos-order-items.cancel');

        Route::get('kitchen', [KitchenController::class, 'index'])->name('pos-kitchen.index');
        Route::get('kitchen/feed', [KitchenController::class, 'feed'])->name('pos-kitchen.feed');
        Route::patch('kitchen/items/{order_item}', [KitchenController::class, 'update'])->name('pos-kitchen.update');
        Route::patch('kitchen/items/{order_item}/cancel', [KitchenController::class, 'cancel'])->name('pos-kitchen.cancel');
        Route::patch('kitchen/items/{order_item}/mark-unavailable', [KitchenController::class, 'markUnavailable'])->name('pos-kitchen.mark-unavailable');

        Route::get('ready-to-serve', [ServingController::class, 'index'])->name('pos-serving.index');
        Route::get('ready-to-serve/feed', [ServingController::class, 'feed'])->name('pos-serving.feed');
        Route::patch('ready-to-serve/items/{order_item}/serve', [ServingController::class, 'serve'])->name('pos-serving.serve');
        Route::patch('ready-to-serve/items/{order_item}/acknowledge', [ServingController::class, 'acknowledge'])->name('pos-serving.acknowledge');

        Route::get('billing', [PosBillingController::class, 'index'])->name('pos-billing.index');
        Route::get('billing/{order}', [PosBillingController::class, 'show'])->name('pos-billing.show');
        Route::get('billing/{order}/print', [PosBillingController::class, 'print'])->name('pos-billing.print');
        Route::patch('billing/{order}/discount', [PosBillingController::class, 'discount'])->name('pos-billing.discount');
        Route::post('billing/{order}/request', [PosBillingController::class, 'requestBill'])->name('pos-billing.request');
        Route::post('billing/{order}/complete', [PosBillingController::class, 'complete'])->name('pos-billing.complete');

        Route::get('payments', [PaymentController::class, 'index'])->name('pos-payments.index');
        Route::post('billing/{order}/payments', [PaymentController::class, 'store'])->name('pos-payments.store');
        Route::patch('payments/{payment}/void', [PaymentController::class, 'void'])->name('pos-payments.void');

        Route::get('reports', [PosReportController::class, 'index'])->name('pos-reports.index');
    });
});

Route::fallback(function () {
    abort(404);
});
