<?php

namespace App\Services\AI;

interface AIProvider
{
    public function generate(string $systemPrompt, string $userMessage, array $options = []): string;
}
