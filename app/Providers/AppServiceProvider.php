<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\Psr\Cache\CacheItemPoolInterface::class, function ($app) {
            return new \App\Services\Cache\Psr6CachePool($app->make(\Illuminate\Contracts\Cache\Repository::class));
        });

        $this->app->alias(\Psr\Cache\CacheItemPoolInterface::class, \App\Services\Cache\Psr6CachePool::class);

        $this->app->singleton(\App\Services\Cache\CacheVersionManager::class, function ($app) {
            return new \App\Services\Cache\CacheVersionManager($app->make(\Psr\Cache\CacheItemPoolInterface::class));
        });

        $this->app->bind(
            \App\Contracts\PaymentGatewayInterface::class,
            \App\Services\Payment\PlaceToPayGateway::class
        );

        $this->app->bind(
            \App\Contracts\ProductSpreadsheetServiceInterface::class,
            \App\Services\Product\ProductSpreadsheetService::class
        );

        $this->app->bind(
            \App\Contracts\ReportServiceInterface::class,
            \App\Services\Report\ReportService::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapFour();

        // Model observers for PSR-6 cache invalidation
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\Category::observe(\App\Observers\CategoryObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\Role::observe(\App\Observers\RoleObserver::class);

        // Superadmin bypass: users with admin role or legacy admin role pass all abilities
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return true;
            }

            return null;
        });

        // Granular permission check via Gate
        Gate::after(function ($user, $ability) {
            if (method_exists($user, 'hasPermission')) {
                return $user->hasPermission($ability);
            }

            return false;
        });

        // Custom Blade directives
        Blade::if('role', function ($role) {
            return auth()->check() && auth()->user()->hasRole($role);
        });

        Blade::if('permission', function ($permission) {
            return auth()->check() && auth()->user()->hasPermission($permission);
        });
    }
}
