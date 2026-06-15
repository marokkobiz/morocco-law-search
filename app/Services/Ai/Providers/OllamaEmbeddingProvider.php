<?php

namespace App\Services\Ai\Providers;

use App\Contracts\Ai\EmbeddingProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OllamaEmbeddingProvider implements EmbeddingProvider
{
    private ?string $lastError = null;

    public function isEnabled(): bool
    {
        return (bool) config('ai.semantic_search.enabled', true);
    }

    public function model(): string
    {
        return (string) config('ai.providers.ollama.embedding_model', 'nomic-embed-text');
    }

    public function embed(string $text): ?array
    {
        $text = $this->normalizeText($text);
        $this->lastError = null;

        if (!$this->isEnabled() || $text === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('ai.providers.ollama.base_url', 'http://localhost:11434'), '/');
        $timeout = (int) config('ai.providers.ollama.embedding_timeout', 30);

        try {
            $response = Http::timeout($timeout)
                ->retry(1, 200)
                ->post($baseUrl.'/api/embed', [
                    'model' => $this->model(),
                    'input' => $text,
                ]);

            if ($response->successful()) {
                return $this->normalizeVector(data_get($response->json(), 'embeddings.0'));
            }

            if ($response->status() !== 404) {
                return $this->fail('Ollama /api/embed request failed', $response->status(), $response->body());
            }

            $legacyResponse = Http::timeout($timeout)
                ->retry(1, 200)
                ->post($baseUrl.'/api/embeddings', [
                    'model' => $this->model(),
                    'prompt' => $text,
                ]);

            if (!$legacyResponse->successful()) {
                return $this->fail('Ollama embedding request failed', $legacyResponse->status(), $legacyResponse->body());
            }

            return $this->normalizeVector(data_get($legacyResponse->json(), 'embedding'));
        } catch (Throwable $error) {
            $this->lastError = $error->getMessage();
            Log::warning('Ollama embedding unavailable', ['message' => $error->getMessage()]);

            return null;
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function normalizeVector(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $vector = collect($value)
            ->filter(fn (mixed $item): bool => is_numeric($item))
            ->map(fn (mixed $item): float => (float) $item)
            ->values()
            ->all();

        return count($vector) >= 2 ? $vector : null;
    }

    private function normalizeText(string $text): string
    {
        return Str::of($text)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(6000, '')
            ->toString();
    }

    private function fail(string $message, int $status, string $body): null
    {
        $body = Str::limit(trim($body), 500, '');
        $this->lastError = trim("{$message}; status={$status}; body={$body}");
        Log::warning($message, [
            'status' => $status,
            'body' => $body,
        ]);

        return null;
    }
}
