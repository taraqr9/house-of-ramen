<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataReviewController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PhoneController;
use App\Http\Controllers\PhoneDataDashboardController;
use App\Http\Controllers\PhoneImageController;
use App\Http\Controllers\PhoneImportRunController;
use App\Http\Controllers\PhoneSourceController;
use App\Http\Controllers\PhoneStoreController;
use App\Http\Controllers\PhoneVariantController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\BrandController as PublicBrandController;
use App\Http\Controllers\Public\CompareController;
use App\Http\Controllers\Public\FindMyPhoneController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PhoneController as PublicPhoneController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SeoLandingPageController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Services\SeoLanding\SeoLandingPageRegistry;
use Illuminate\Support\Facades\Route;

/*
 * Public recommendation API - no auth required. Kept as a stable JSON
 * contract for future clients (kept separate from the Inertia-rendered
 * Find My Phone flow below, which calls the same engine directly so its
 * results page can be server-rendered in one request/response).
 */
Route::post('/recommendations', [RecommendationController::class, 'recommend'])->name('recommendations.create');

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
 * Public Phone Kinbo website - consumer-facing, no auth, completely
 * separate visually from the admin panel (see resources/js/Pages/Public
 * and Layouts/PublicLayout.vue). Route names are prefixed "public." so
 * they never collide with the admin's resource route names below.
 */
Route::name('public.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::post('/find-my-phone/results', [FindMyPhoneController::class, 'results'])->name('find-my-phone.results');
    Route::get('/find-my-phone/results', [FindMyPhoneController::class, 'resultsFallback'])->name('find-my-phone.results-fallback');

    Route::get('/phones', [PublicPhoneController::class, 'index'])->name('phones.index');
    // Must stay before the {phone:slug} catch-all below - both are
    // 2-segment paths under /phones, so registration order decides which
    // one matches a request for /phones/search.
    Route::get('/phones/search', [PublicPhoneController::class, 'search'])->name('phones.search');
    Route::get('/phones/brand/{brand:slug}', [PublicBrandController::class, 'show'])->name('phones.brand');
    Route::get('/phones/{phone:slug}', [PublicPhoneController::class, 'show'])->name('phones.show');

    Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');

    Route::get('/about', [AboutController::class, 'index'])->name('about');

    // Search-intent landing pages ("/best-phones-under-15000" etc.) - one
    // controller for every approved page (see SeoLandingPageRegistry). The
    // regex constraint means this route only ever matches a registered
    // slug, so adding a page is a registry entry, never a new route/
    // controller, and an unregistered top-level path can never be
    // shadowed by this catch-most pattern.
    Route::get('/{seoSlug}', [SeoLandingPageController::class, 'show'])
        ->where('seoSlug', SeoLandingPageRegistry::routePattern())
        ->name('seo-landing.show');
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
    // Phone Kinbo site - the route NAME stays "dashboard" so every existing
    // route()/redirect()->route() call and the DB-driven sidebar menu (which
    // stores route names, not raw URLs) keep working unchanged.
    Route::get('/admin', [DashboardController::class, 'index'])
        ->name('dashboard');

    // The GA4-backed reporting page, split out from the main dashboard so
    // /admin stays focused on catalogue/admin work - same "dashboard-view"
    // permission gate, same AnalyticsDashboardService, just its own URL and
    // sidebar entry (see database/seeders/MenuSeeder.php).
    Route::get('/admin/analytics', [AnalyticsController::class, 'index'])
        ->name('analytics.index');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::post('{notification}/read', [NotificationController::class, 'read'])->name('read');
    });

    Route::group(['prefix' => 'phone-data'], function () {
        Route::get('/', [PhoneDataDashboardController::class, 'index'])->name('phone-data.dashboard');

        Route::resource('brands', BrandController::class)->except(['show']);
        Route::resource('phone-stores', PhoneStoreController::class)->except(['show'])->parameters(['phone-stores' => 'phone_store']);

        Route::resource('phones', PhoneController::class)->except(['show']);
        Route::prefix('phones/{phone}')->name('phones.')->group(function () {
            Route::get('variants/create', [PhoneVariantController::class, 'create'])->name('variants.create');
            Route::post('variants', [PhoneVariantController::class, 'store'])->name('variants.store');
            Route::get('variants/{variant}/edit', [PhoneVariantController::class, 'edit'])->name('variants.edit');
            Route::put('variants/{variant}', [PhoneVariantController::class, 'update'])->name('variants.update');
            Route::delete('variants/{variant}', [PhoneVariantController::class, 'destroy'])->name('variants.destroy');

            Route::post('images', [PhoneImageController::class, 'store'])->name('images.store');
            Route::post('images/{image}/set-primary', [PhoneImageController::class, 'setPrimary'])->name('images.set-primary');
            Route::post('images/{image}/verify', [PhoneImageController::class, 'verify'])->name('images.verify');
            Route::post('images/{image}/reject', [PhoneImageController::class, 'reject'])->name('images.reject');
            Route::delete('images/{image}', [PhoneImageController::class, 'destroy'])->name('images.destroy');
        });

        Route::get('sources', [PhoneSourceController::class, 'index'])->name('phone-sources.index');
        Route::get('sources/{phone_source}/edit', [PhoneSourceController::class, 'edit'])->name('phone-sources.edit');
        Route::put('sources/{phone_source}', [PhoneSourceController::class, 'update'])->name('phone-sources.update');

        Route::get('import-runs', [PhoneImportRunController::class, 'index'])->name('phone-import-runs.index');
        Route::get('import-runs/{phone_import_run}', [PhoneImportRunController::class, 'show'])->name('phone-import-runs.show');

        Route::get('review', [DataReviewController::class, 'index'])->name('data-review.index');
        Route::post('review/reviews/{review}/resolve', [DataReviewController::class, 'resolveReview'])->name('data-review.reviews.resolve');
        Route::post('review/conflicts/{conflict}/resolve', [DataReviewController::class, 'resolveConflict'])->name('data-review.conflicts.resolve');
        Route::post('review/sources/{phone_source}/mark-unreliable', [DataReviewController::class, 'markSourceUnreliable'])->name('data-review.sources.mark-unreliable');
    });
});

Route::fallback(function () {
    abort(404);
});
