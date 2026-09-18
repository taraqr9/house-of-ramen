<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MenuController as PublicMenuController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\RestaurantGalleryImageController;
use App\Http\Controllers\RestaurantMenuCategoryController;
use App\Http\Controllers\RestaurantMenuItemController;
use App\Http\Controllers\RestaurantPopupOfferController;
use App\Http\Controllers\RestaurantVideoFeatureController;
use App\Http\Controllers\RoleController;
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
    Route::resource('menus', MenuController::class);

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
    });
});

Route::fallback(function () {
    abort(404);
});
