<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CurrentTenant;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register CurrentTenant as a singleton
        $this->app->singleton(CurrentTenant::class, function () {
            return new CurrentTenant();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
