<?php

namespace App\Services\AI;

use InvalidArgumentException;

class AIProviderFactory
{
    public function make(string $provider = null): AIProvider
    {
        $provider = $provider ?? config('crawler.ai_provider', 'ollama');

        return match ($provider) {
            'ollama' => new OllamaProvider(
                baseUrl: config('crawler.ollama_url', 'http://localhost:11434'),
                model: config('crawler.ollama_model', 'qwen3:8b'),
                timeout: (int) config('crawler.ollama_timeout', 300),
            ),
            'openrouter', 'groq', 'openai', 'github', 'togetherai' => new OpenAICompatibleProvider(
                baseUrl: config('crawler.ai_base_url', 'http://localhost:11434/v1'),
                model: config('crawler.ai_model', 'qwen3:8b'),
                timeout: (int) config('crawler.ai_timeout', 300),
                apiKey: config('crawler.ai_api_key', ''),
            ),
            default => throw new InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }
}
