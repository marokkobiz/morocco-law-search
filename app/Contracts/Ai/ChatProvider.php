<?php

namespace App\Contracts\Ai;

interface ChatProvider
{
    public function isEnabled(): bool;

    public function model(): string;

    public function chat(string $message, array $context = []): string;

    public function lastError(): ?string;
}
