<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaProvider implements AIProvider
{
    public function __construct(
        private string $baseUrl,
        private string $model,
        private int $timeout,
    ) {}

    public function generate(string $systemPrompt, string $userMessage, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;

        $response = Http::timeout($this->timeout)
            ->post(rtrim($this->baseUrl, '/') . '/api/generate', [
                'model' => $model,
                'prompt' => $systemPrompt . "\n\n--- RAW TEXT ---\n" . $userMessage,
                'stream' => false,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Ollama API returned status ' . $response->status() . ': ' . $response->body()
            );
        }

        $body = $response->json();
        return $body['response'] ?? throw new RuntimeException('Ollama response missing "response" key');
    }
}
