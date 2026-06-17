<?php

namespace App\Providers;

use App\Services\AI\AIProviderFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AIProviderFactory::class, function () {
            return new AIProviderFactory;
        });

        $this->app->bind(\App\Services\AI\AIProvider::class, function ($app) {
            return $app->make(AIProviderFactory::class)->make();
        });
    }

    public function boot(): void
    {
        //
    }
}
