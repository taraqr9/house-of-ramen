<?php

namespace App\Providers;

use App\Models\Menu;
use App\Policies\RolePolicy;
use App\Services\Analytics\Contracts\AnalyticsReportClient;
use App\Services\Analytics\GA4AnalyticsReportClient;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The admin dashboard's Analytics section (App\Services\Analytics\
        // AnalyticsDashboardService) depends on this interface, never the
        // concrete GA4 client directly - tests bind a fake here instead
        // (see tests/Feature/Analytics/*), so the real Google Analytics
        // Data API is never called during the test suite.
        $this->app->singleton(AnalyticsReportClient::class, fn () => GA4AnalyticsReportClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        View::composer('partials.sidebar', function ($view) {
            $menus = Menu::query()
                ->whereNull('parent_id')
                ->where('is_active', 1)
                ->orderBy('serial')
                ->with([
                    'children' => function ($q) {
                        $q->where('is_active', 1)
                            ->orderBy('serial');
                    },
                ])
                ->get();

            $view->with('menus', $menus);
        });

        View::composer('partials.nav', function ($view) {
            if (! auth()->check()) {
                $view->with(['navNotifications' => collect(), 'navUnreadCount' => 0]);

                return;
            }

            $view->with([
                'navNotifications' => auth()->user()->notifications()->latest()->take(5)->get(),
                'navUnreadCount' => auth()->user()->unreadNotifications()->count(),
            ]);
        });
    }
}
