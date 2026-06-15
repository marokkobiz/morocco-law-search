<?php

namespace App\Services\Ai\Providers;

use App\Contracts\Ai\ChatProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OllamaChatProvider implements ChatProvider
{
    private ?string $lastError = null;

    public function isEnabled(): bool
    {
        return (bool) config('ai.chat.enabled', false);
    }

    public function model(): string
    {
        return (string) config('ai.providers.ollama.model', 'qwen3:8b');
    }

    public function chat(string $message, array $context = []): string
    {
        $this->lastError = null;

        if (!$this->isEnabled()) {
            return '';
        }

        $baseUrl = rtrim((string) config('ai.providers.ollama.base_url', 'http://localhost:11434'), '/');
        $timeout = (int) config('ai.providers.ollama.chat_timeout', 120);

        $messages = [];

        $messages[] = [
            'role' => 'system',
            'content' => 'You are a text analysis tool. You only answer based on the excerpts given below. Never use your own knowledge.',
        ];

        $excerpts = '';
        foreach ($context as $i => $chunk) {
            $excerpts .= "[{$i}] {$chunk['source']}\n{$chunk['content']}\n\n";
        }

        $userMsg = "Excerpts:\n\n{$excerpts}\n---\nQuestion: {$message}\n\n"
            . 'Rules: Answer ONLY from the excerpts above. Cite as [0], [1], etc. '
            . 'If the excerpts do not answer the question, say only: "The provided documents do not contain information on this topic." '
            . 'Never recommend consulting a professional. Never use outside knowledge. Never add disclaimers.';

        $messages[] = ['role' => 'user', 'content' => $userMsg];

        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'stream' => false,
            'temperature' => 0.0,
            'num_predict' => 2048,
        ];

        try {
            $response = Http::timeout($timeout)->post($baseUrl.'/api/chat', $payload);

            if ($response->successful()) {
                return $response->json('message.content') ?? '';
            }

            $this->lastError = 'Ollama chat request failed: status=' . $response->status();
            Log::warning('Ollama chat request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return '';
        } catch (Throwable $error) {
            $this->lastError = $error->getMessage();
            Log::warning('Ollama chat unavailable', ['message' => $error->getMessage()]);

            return '';
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }
}
