<?php

namespace App\Contracts\Ai;

interface EmbeddingProvider
{
    public function isEnabled(): bool;

    public function model(): string;

    public function embed(string $text): ?array;

    public function lastError(): ?string;
}
