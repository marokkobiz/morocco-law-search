<?php

namespace App\Support;

use ArPHP\I18N\Arabic;

final class PdfArabic
{
    private static ?Arabic $arabic = null;

    /**
     * Shape Arabic text for dompdf (which cannot shape/bidi Arabic itself).
     *
     * Non-Arabic strings are returned untouched. Western digits are preserved
     * (Moroccan convention) instead of being converted to Arabic-Indic forms.
     *
     * The wrap limit is set high so ar-php keeps each string on a single
     * visual line: ar-php reverses every wrapped line independently, which
     * garbles long multi-sentence paragraphs (e.g. the payment note). dompdf
     * wraps the single shaped line at the container width instead.
     */
    public static function shape(string $text, string $locale): string
    {
        if ($locale !== 'ar' || ! preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return $text;
        }

        return self::instance()->utf8Glyphs($text, 1000, false, false);
    }

    private static function instance(): Arabic
    {
        return self::$arabic ??= new Arabic('Glyphs');
    }
}
