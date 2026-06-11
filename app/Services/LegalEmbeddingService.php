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

    /**
     * Embed several texts in one Ollama request. Returns vectors aligned with
     * the input order, or null when the batch call failed entirely.
     *
     * @param list<string> $texts
     * @return list<?array>|null
     */
    public function embedBatch(array $texts): ?array
    {
        $texts = array_map(fn (string $text): string => $this->normalizeEmbeddingText($text), $texts);
        $this->lastError = null;

        if (!$this->isEnabled() || !$texts) {
            return null;
        }

        $baseUrl = rtrim((string) config('legal_ai.embeddings.base_url', 'http://localhost:11434'), '/');
        $timeout = max(60, (int) config('legal_ai.embeddings.timeout_seconds', 30));

        try {
            $response = Http::timeout($timeout)
                ->retry(1, 200)
                ->post($baseUrl.'/api/embed', [
                    'model' => $this->model(),
                    'input' => array_values($texts),
                ]);

            if (!$response->successful()) {
                return $this->recordFailure('Ollama batch embed request failed', $response->status(), $response->body());
            }

            $embeddings = data_get($response->json(), 'embeddings');

            if (!is_array($embeddings) || count($embeddings) !== count($texts)) {
                $this->lastError = 'Ollama batch embed returned a mismatched embedding count.';

                return null;
            }

            return array_map(fn (mixed $vector): ?array => $this->normalizeVector($vector), $embeddings);
        } catch (Throwable $error) {
            $this->lastError = $error->getMessage();
            Log::warning('Ollama batch embedding unavailable', ['message' => $error->getMessage()]);

            return null;
        }
    }

    public function embed(string $text): ?array
    {
        $text = $this->normalizeEmbeddingText($text);
        $this->lastError = null;

        if (!$this->isEnabled() || $text === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('legal_ai.embeddings.base_url', 'http://localhost:11434'), '/');
        $timeout = (int) config('legal_ai.embeddings.timeout_seconds', 30);

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
                return $this->recordFailure('Ollama /api/embed request failed', $response->status(), $response->body());
            }

            $legacyResponse = Http::timeout($timeout)
                ->retry(1, 200)
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

    /**
     * Sign-quantize a float vector into a bit string (1 bit per dimension),
     * used for fast Hamming-distance candidate scans.
     */
    public function binarizeVector(array $vector): ?string
    {
        $vector = $this->normalizeVector($vector);

        if (!$vector) {
            return null;
        }

        $code = '';
        $byte = 0;
        $count = 0;

        foreach ($vector as $value) {
            $byte = ($byte << 1) | ($value > 0.0 ? 1 : 0);

            if (++$count === 8) {
                $code .= chr($byte);
                $byte = 0;
                $count = 0;
            }
        }

        if ($count > 0) {
            $code .= chr(($byte << (8 - $count)) & 0xFF);
        }

        return $code;
    }

    /**
     * Rank [id => binary code] rows by Hamming distance to the query code and
     * return the closest $limit ids (ascending distance).
     *
     * @param iterable<object{id: int|string, code: string}> $codeRows
     * @return list<int>
     */
    public function topHammingCandidates(string $queryCode, iterable $codeRows, int $limit): array
    {
        static $popcount = null;

        if ($popcount === null) {
            $popcount = [];

            for ($byte = 0; $byte < 256; $byte++) {
                $popcount[$byte] = substr_count(decbin($byte), '1');
            }
        }

        $length = strlen($queryCode);
        $distances = [];

        foreach ($codeRows as $row) {
            $code = (string) $row->code;

            if (strlen($code) !== $length) {
                continue;
            }

            $xor = $code ^ $queryCode;
            $distance = 0;

            foreach (count_chars($xor, 1) as $byte => $occurrences) {
                $distance += $popcount[$byte] * $occurrences;
            }

            $distances[(int) $row->id] = $distance;
        }

        asort($distances);

        return array_map('intval', array_keys(array_slice($distances, 0, $limit, true)));
    }

    public function packVector(array $vector): ?string
    {
        $vector = $this->normalizeVector($vector);

        return $vector ? pack('g*', ...$vector) : null;
    }

    public function unpackVector(mixed $value): ?array
    {
        if (!is_string($value) || $value === '' || strlen($value) % 4 !== 0) {
            return null;
        }

        $vector = unpack('g*', $value);

        return $vector ? $this->normalizeVector(array_values($vector)) : null;
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
