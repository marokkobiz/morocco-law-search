<?php

namespace App\Services;

class SearchTextNormalizer
{
    private const ARABIC_LETTER_MAP = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و',
        'ة' => 'ه',
    ];

    private const LATIN_ACCENT_MAP = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
        'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'ñ' => 'n',
        'œ' => 'oe', 'æ' => 'ae',
    ];

    /**
     * Normalize text for indexing and matching: lowercase, fold French
     * accents, unify Arabic letter variants, strip Arabic diacritics and
     * tatweel, drop punctuation, collapse whitespace.
     */
    public function normalize(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, self::LATIN_ACCENT_MAP);
        $text = strtr($text, self::ARABIC_LETTER_MAP);
        // Arabic harakat, superscript alef, and tatweel carry no search meaning.
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return list<string> unique normalized tokens, longest first
     */
    public function tokens(string $text, int $minLength = 2): array
    {
        $normalized = $this->normalize($text);

        if ($normalized === '') {
            return [];
        }

        $tokens = array_values(array_unique(array_filter(
            explode(' ', $normalized),
            fn (string $token): bool => mb_strlen($token, 'UTF-8') >= $minLength
        )));

        usort($tokens, fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        return $tokens;
    }
}
