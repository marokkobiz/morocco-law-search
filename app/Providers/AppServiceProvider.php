<?php

namespace App\Providers;

use App\Contracts\Ai\ChatProvider;
use App\Contracts\Ai\EmbeddingProvider;
use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderFactory::class);

        $this->app->bind(EmbeddingProvider::class, fn ($app) =>
            $app->make(AiProviderFactory::class)->makeEmbeddingProvider()
        );

        $this->app->bind(ChatProvider::class, fn ($app) =>
            $app->make(AiProviderFactory::class)->makeChatProvider()
        );
    }

    public function boot(): void
    {
        //
    }
}
