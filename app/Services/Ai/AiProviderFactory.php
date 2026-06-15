<?php

namespace App\Services\Ai;

use App\Contracts\Ai\ChatProvider;
use App\Contracts\Ai\EmbeddingProvider;
use App\Services\Ai\Providers\OllamaChatProvider;
use App\Services\Ai\Providers\OllamaEmbeddingProvider;
use RuntimeException;

class AiProviderFactory
{
    public function makeEmbeddingProvider(): EmbeddingProvider
    {
        return match ($this->getProvider()) {
            'ollama' => app(OllamaEmbeddingProvider::class),
            default => throw new RuntimeException("Unknown AI provider: {$this->getProvider()}"),
        };
    }

    public function makeChatProvider(): ChatProvider
    {
        return match ($this->getProvider()) {
            'ollama' => app(OllamaChatProvider::class),
            default => throw new RuntimeException("Unknown AI provider: {$this->getProvider()}"),
        };
    }

    private function getProvider(): string
    {
        return strtolower(config('ai.default_provider', 'ollama'));
    }
}
