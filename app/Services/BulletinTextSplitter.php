<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Splits the raw text of a Bulletin Officiel issue into individual legal
 * acts (dahirs, lois, décrets, arrêtés, décisions) so they can be imported
 * as standalone searchable documents.
 */
class BulletinTextSplitter
{
    private const ACT_TYPES = '(?:Dahir|D[ée]cret-loi|D[ée]cret|Arr[êe]t[ée]|D[ée]cision|Loi)';

    private const PURPOSE_WORDS = [
        'portant', 'relatif', 'relative', 'fixant', 'modifiant', 'complétant', 'completant',
        'approuvant', 'autorisant', 'instituant', 'abrogeant', 'réglementant', 'reglementant',
        'déterminant', 'determinant', 'concernant', 'étendant', 'etendant', 'homologuant',
        "pris pour l'application", 'attribuant', 'accordant', 'déclarant', 'declarant',
    ];

    /**
     * @return list<array{type: string, title: string, reference: ?string, text: string}>
     */
    public function split(string $rawText): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $rawText) ?? $rawText);

        if ($text === '') {
            return [];
        }

        $boundaries = $this->headingOffsets($text);

        if (!$boundaries) {
            return [];
        }

        $segments = [];

        foreach ($boundaries as $index => $offset) {
            $end = $boundaries[$index + 1] ?? mb_strlen($text);
            $segment = trim(mb_substr($text, $offset, $end - $offset));

            if (mb_strlen($segment) < 400) {
                continue;
            }

            $segments[] = [
                'type' => $this->actType($segment),
                'title' => $this->actTitle($segment),
                'reference' => $this->actReference($segment),
                'text' => $segment,
            ];
        }

        return $segments;
    }

    /**
     * Offsets (in characters) of act headings: a capitalized act keyword,
     * an official number nearby, and a purpose verb — which distinguishes
     * "Décret n° 2-24-743 du ... modifiant ..." (heading) from references
     * like "vu le décret n° 2-16-172 susvisé".
     *
     * @return list<int>
     */
    private function headingOffsets(string $text): array
    {
        if (!preg_match_all(
            '/(?<=^|[.»;] |\s{2})('.self::ACT_TYPES.')(?=\s)/u',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return [];
        }

        $offsets = [];

        foreach ($matches[1] as [$keyword, $byteOffset]) {
            // Headings start with an uppercase letter; references are lowercase.
            if (!preg_match('/^\p{Lu}/u', $keyword)) {
                continue;
            }

            $charOffset = mb_strlen(substr($text, 0, $byteOffset));
            $window = mb_substr($text, $charOffset, 350);
            $windowLower = mb_strtolower($window, 'UTF-8');

            // "Loi" headings are rare in the BO body (laws arrive via dahirs
            // of promulgation); require the number immediately for them.
            $numberPattern = mb_strtolower($keyword) === 'loi'
                ? '/^loi\s+n\s*[°º]/iu'
                : '/n\s*[°º]\s*[\d][\d.\-]*|du\s+\d/iu';

            if (!preg_match($numberPattern, $windowLower)) {
                continue;
            }

            if (!Str::contains($windowLower, self::PURPOSE_WORDS)) {
                continue;
            }

            $offsets[] = $charOffset;
        }

        return array_values(array_unique($offsets));
    }

    private function actType(string $segment): string
    {
        $head = mb_strtolower(mb_substr($segment, 0, 30), 'UTF-8');

        return match (true) {
            str_starts_with($head, 'dahir') => 'dahir',
            str_starts_with($head, 'décret-loi'), str_starts_with($head, 'decret-loi') => 'decret-loi',
            str_starts_with($head, 'décret'), str_starts_with($head, 'decret') => 'decret',
            str_starts_with($head, 'arrêté'), str_starts_with($head, 'arrete'), str_starts_with($head, 'arrête') => 'arrete',
            str_starts_with($head, 'décision'), str_starts_with($head, 'decision') => 'decision',
            str_starts_with($head, 'loi') => 'loi',
            default => 'loi',
        };
    }

    private function actTitle(string $segment): string
    {
        $title = mb_substr($segment, 0, 400);

        // The operative text starts at the first article, the "Vu" recitals,
        // or the signing-authority boilerplate.
        foreach ([
            ' Article premier', ' ARTICLE PREMIER', ' Article 1', ' Vu ', ' VU ', ' EXPOSE', ' Après délibération',
            ' LE CHEF DU GOUVERNEMENT', ' Le chef du gouvernement', ' LE PREMIER MINISTRE', ' Le Premier ministre',
            ' LE MINISTRE', ' Le ministre', ' LA MINISTRE', ' La ministre', ' LE SECRETAIRE', ' LOUANGE A DIEU',
        ] as $stop) {
            $position = mb_strpos($title, $stop);

            if ($position !== false && $position > 40) {
                $title = mb_substr($title, 0, $position);
            }
        }

        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title, " \t.;,–-");

        return Str::limit($title, 220, '…');
    }

    private function actReference(string $segment): ?string
    {
        if (preg_match('/n\s*[°º]\s*([\d][\d.\-]*\d|\d)/u', mb_substr($segment, 0, 200), $match)) {
            return Str::limit($match[1], 60, '');
        }

        return null;
    }
}
