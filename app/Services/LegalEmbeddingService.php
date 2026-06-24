<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LegalEmbeddingService
{
    private ?string $lastError = null;

    public function isEnabled(): bool
    {
        return (bool) config('legal_ai.semantic_search.enabled', true)
            && strtolower((string) config('legal_ai.embeddings.provider', 'ollama')) === 'ollama';
    }

    public function model(): string
    {
        return (string) config('legal_ai.embeddings.model', 'nomic-embed-text');
    }

    public function checksum(string $text): string
    {
        return hash('sha256', $this->normalizeEmbeddingText($text));
    }

    public function embed(string $text): ?array
    {
        $text = $this->normalizeEmbeddingText($text);
        $this->lastError = null;

        if (!$this->isEnabled() || $text === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('legal_ai.embeddings.base_url', 'http://127.0.0.1:11434'), '/');
        $timeout = (int) config('legal_ai.embeddings.timeout_seconds', 120);

        try {
            $response = $this->client()
                ->post($baseUrl.'/api/embed', [
                    'model' => $this->model(),
                    'input' => $text,
                ]);

            if ($response->successful()) {
                return $this->normalizeVector(data_get($response->json(), 'embeddings.0'));
            }

            if ($response->status() !== 404) {
                return $this->recordFailure('Ollama /api/embed request failed', $response->status(), $response->body());
            }

            $legacyResponse = $this->client()
                ->post($baseUrl.'/api/embeddings', [
                    'model' => $this->model(),
                    'prompt' => $text,
                ]);

            if (!$legacyResponse->successful()) {
                return $this->recordFailure('Ollama embedding request failed', $legacyResponse->status(), $legacyResponse->body());
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

    /**
     * HTTP client tuned for a local Ollama instance: explicit connect timeout
     * (so a dead IPv6 address fails fast) plus retries with linear backoff to
     * ride out transient connection blips without failing the whole document.
     */
    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $timeout = (int) config('legal_ai.embeddings.timeout_seconds', 120);
        $connectTimeout = max(1, (int) config('legal_ai.embeddings.connect_timeout_seconds', 15));
        $retries = max(1, (int) config('legal_ai.embeddings.max_retries', 3));

        return Http::connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->retry($retries, fn (int $attempt): int => $attempt * 1000, throw: false);
    }

    public function cosineSimilarity(array $left, array $right): float
    {
        $left = $this->normalizeVector($left) ?? [];
        $right = $this->normalizeVector($right) ?? [];

        if (!$left || count($left) !== count($right)) {
            return 0.0;
        }

        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        foreach ($left as $index => $value) {
            $other = $right[$index];
            $dot += $value * $other;
            $leftMagnitude += $value * $value;
            $rightMagnitude += $other * $other;
        }

        if ($leftMagnitude <= 0.0 || $rightMagnitude <= 0.0) {
            return 0.0;
        }

        return round($dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude)), 6);
    }

    public function decodeStoredVector(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return $this->normalizeVector($value);
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

    private function recordFailure(string $message, int $status, string $body): null
    {
        $body = Str::limit(trim($body), 500, '');
        $this->lastError = trim("{$message}; status={$status}; body={$body}");
        Log::warning($message, [
            'status' => $status,
            'body' => $body,
        ]);

        return null;
    }

    private function normalizeEmbeddingText(string $text): string
    {
        return Str::of($text)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(6000, '')
            ->toString();
    }
}
