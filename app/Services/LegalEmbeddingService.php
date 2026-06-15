<?php

namespace App\Services;

use App\Contracts\Ai\EmbeddingProvider;
use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Str;

class LegalEmbeddingService
{
    private readonly EmbeddingProvider $provider;

    private ?string $lastError = null;

    public function __construct(AiProviderFactory $factory)
    {
        $this->provider = $factory->makeEmbeddingProvider();
    }

    public function isEnabled(): bool
    {
        return $this->provider->isEnabled();
    }

    public function model(): string
    {
        return $this->provider->model();
    }

    public function checksum(string $text): string
    {
        return hash('sha256', $this->normalizeEmbeddingText($text));
    }

    public function embed(string $text): ?array
    {
        $this->lastError = $this->provider->lastError();

        return $this->provider->embed($text);
    }

    public function lastError(): ?string
    {
        return $this->provider->lastError() ?? $this->lastError;
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

    private function normalizeEmbeddingText(string $text): string
    {
        return Str::of($text)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(6000, '')
            ->toString();
    }
}
