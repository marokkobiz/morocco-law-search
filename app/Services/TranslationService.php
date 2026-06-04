<?php

namespace App\Services;

use App\Models\Law;
use Illuminate\Support\Facades\Http;
use Throwable;

class TranslationService
{
    private const MAX_CHUNK_LENGTH = 3800;

    public function translate(Law $law, string $targetLanguage): array
    {
        $sourceLanguage = $law->language ?: 'fr';
        $targetLanguage = strtolower(trim($targetLanguage)) ?: 'en';

        if ($sourceLanguage === $targetLanguage) {
            return [
                'sourceLanguage' => $sourceLanguage,
                'targetLanguage' => $targetLanguage,
                'translatedTitle' => $law->title,
                'translatedContent' => $law->content,
                'provider' => 'source',
            ];
        }

        $title = $this->translateText($law->title, $sourceLanguage, $targetLanguage);
        $content = $this->translateText($law->content, $sourceLanguage, $targetLanguage);
        $providers = array_values(array_unique(array_filter([$title['provider'], $content['provider']])));

        return [
            'sourceLanguage' => $sourceLanguage,
            'targetLanguage' => $targetLanguage,
            'translatedTitle' => $title['text'],
            'translatedContent' => $content['text'],
            'provider' => implode(',', $providers) ?: 'public-translation',
        ];
    }

    public function buildExternalTranslationUrl(Law $law, string $targetLanguage): string
    {
        $text = trim(($law->title ?? '')."\n\n".($law->content ?? ''));

        return 'https://translate.google.com/?sl='.urlencode($law->language ?: 'fr')
            .'&tl='.urlencode($targetLanguage)
            .'&text='.urlencode($text)
            .'&op=translate';
    }

    private function translateText(?string $text, string $sourceLanguage, string $targetLanguage): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return ['text' => '', 'provider' => 'source'];
        }

        $translatedChunks = [];
        $providers = [];

        foreach ($this->chunkText($text) as $chunk) {
            $translated = $this->translateChunk($chunk, $sourceLanguage, $targetLanguage);
            $translatedChunks[] = $translated['text'];
            $providers[] = $translated['provider'];
        }

        return [
            'text' => trim(implode("\n\n", $translatedChunks)),
            'provider' => implode(',', array_values(array_unique($providers))),
        ];
    }

    private function translateChunk(string $text, string $sourceLanguage, string $targetLanguage): array
    {
        foreach (['google', 'mymemory'] as $provider) {
            try {
                $translated = $provider === 'google'
                    ? $this->translateWithGoogle($text, $sourceLanguage, $targetLanguage)
                    : $this->translateWithMyMemory($text, $sourceLanguage, $targetLanguage);

                if ($translated !== '') {
                    return ['text' => $translated, 'provider' => $provider];
                }
            } catch (Throwable) {
                continue;
            }
        }

        throw new TranslationUnavailableException('Free translation providers are unavailable.');
    }

    private function translateWithGoogle(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        $response = Http::timeout($this->timeoutSeconds())->get('https://translate.googleapis.com/translate_a/single', [
            'client' => 'gtx',
            'sl' => $sourceLanguage,
            'tl' => $targetLanguage,
            'dt' => 't',
            'q' => $text,
        ]);

        if (!$response->successful()) {
            throw new TranslationUnavailableException('Google public translation endpoint failed.');
        }

        $payload = $response->json();
        $segments = is_array($payload[0] ?? null) ? $payload[0] : [];
        $translated = collect($segments)
            ->map(fn (mixed $segment): string => is_array($segment) ? (string) ($segment[0] ?? '') : '')
            ->implode('');

        return trim($translated);
    }

    private function translateWithMyMemory(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        $response = Http::timeout($this->timeoutSeconds())->get('https://api.mymemory.translated.net/get', [
            'q' => $text,
            'langpair' => "{$sourceLanguage}|{$targetLanguage}",
        ]);

        if (!$response->successful()) {
            throw new TranslationUnavailableException('MyMemory translation endpoint failed.');
        }

        return trim((string) data_get($response->json(), 'responseData.translatedText', ''));
    }

    private function chunkText(string $text): array
    {
        $paragraphs = preg_split('/\n{2,}/', $text) ?: [$text];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > self::MAX_CHUNK_LENGTH) {
                $this->flushChunk($chunks, $current);
                $current = '';

                foreach ($this->splitLongText($paragraph) as $part) {
                    $chunks[] = $part;
                }

                continue;
            }

            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            if (mb_strlen($candidate) > self::MAX_CHUNK_LENGTH) {
                $this->flushChunk($chunks, $current);
                $current = $paragraph;
            } else {
                $current = $candidate;
            }
        }

        $this->flushChunk($chunks, $current);

        return $chunks ?: [$text];
    }

    private function splitLongText(string $text): array
    {
        $parts = [];
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; $offset += self::MAX_CHUNK_LENGTH) {
            $parts[] = mb_substr($text, $offset, self::MAX_CHUNK_LENGTH);
        }

        return $parts;
    }

    private function flushChunk(array &$chunks, string $chunk): void
    {
        $chunk = trim($chunk);

        if ($chunk !== '') {
            $chunks[] = $chunk;
        }
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) env('TRANSLATION_TIMEOUT_SECONDS', 12));
    }
}
