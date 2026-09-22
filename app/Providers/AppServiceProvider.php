<?php

namespace App\Providers;

use App\Models\Menu;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Role is Spatie\Permission\Models\Role, not an App\Models\* class,
        // so Laravel's naming-convention policy auto-discovery doesn't find
        // it - it must be registered explicitly (a bare `$policies` property
        // does nothing here since this provider doesn't extend the
        // Auth-specific base provider that reads it).
        Gate::policy(Role::class, RolePolicy::class);

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
