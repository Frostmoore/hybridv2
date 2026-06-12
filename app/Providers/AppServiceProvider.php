<?php

namespace App\Providers;

use App\Services\JwtService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JwtService::class, fn () => new JwtService(
            secret: (string) config('hybrid.jwt_secret'),
            expiry: (int) config('hybrid.jwt_expiry'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
