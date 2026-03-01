<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\BlizzardTokenService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BlizzardTokenService::class, function () {
            return new BlizzardTokenService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
