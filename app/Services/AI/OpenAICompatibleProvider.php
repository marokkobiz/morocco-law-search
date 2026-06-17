<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAICompatibleProvider implements AIProvider
{
    public function __construct(
        private string $baseUrl,
        private string $model,
        private int $timeout,
        private string $apiKey = '',
    ) {}

    public function generate(string $systemPrompt, string $userMessage, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;

        $request = Http::timeout($this->timeout)
            ->withOptions(['verify' => false]);

        if ($this->apiKey !== '') {
            $request = $request->withToken($this->apiKey);
        }

        $response = $request->post(
            rtrim($this->baseUrl, '/') . '/chat/completions',
            [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'stream' => false,
            ]
        );

        if (!$response->successful()) {
            throw new RuntimeException(
                'AI API returned status ' . $response->status() . ': ' . $response->body()
            );
        }

        $body = $response->json();
        $text = $body['choices'][0]['message']['content'] ?? null;

        if ($text === null) {
            throw new RuntimeException('AI response missing content: ' . substr($response->body(), 0, 500));
        }

        return $text;
    }
}
