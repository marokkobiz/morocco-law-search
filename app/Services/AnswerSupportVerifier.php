<?php

namespace App\Services;

use Illuminate\Support\Str;

class AnswerSupportVerifier
{
    public function verify(string $answer, array $citations, string $language): string
    {
        if (!$citations || $this->alreadyContainsSourceWarning($answer)) {
            return $answer;
        }

        $audit = $this->audit($answer, $citations, $language);

        if (($audit['status'] ?? 'insufficient_sources') === 'strong_sources') {
            return $answer;
        }

        $repaired = in_array('unsupported_claim', $audit['warnings'] ?? [], true)
            ? $this->removeUnsupportedUncitedSentences($answer, $audit['unsupportedClaims'] ?? [])
            : $answer;

        return trim($repaired).' '.$this->sourceSufficiencyWarning($audit['warnings'] ?? [], $language);
    }

    public function audit(string $answer, array $citations, string $language = 'en'): array
    {
        if (!$citations) {
            return [
                'status' => 'insufficient_sources',
                'language' => $language,
                'warnings' => ['no_citations'],
                'citationCoverage' => [
                    'riskyClaimCount' => 0,
                    'supportedRiskyClaimCount' => 0,
                    'unsupportedRiskyClaimCount' => 0,
                    'weaklySupportedRiskyClaimCount' => 0,
                ],
                'unsupportedClaims' => [],
                'weaklySupportedClaims' => [],
                'citationAudits' => [],
            ];
        }

        $issues = [];
        $unsupportedClaims = [];
        $weaklySupportedClaims = [];
        $riskyClaimCount = 0;
        $supportedRiskyClaimCount = 0;
        $citationTexts = collect($citations)
            ->mapWithKeys(fn (array $citation, int $index): array => [
                $index + 1 => $this->normalizeText(implode(' ', array_filter([
                    $citation['title'] ?? '',
                    $citation['articleNumber'] ?? '',
                    $citation['documentTitle'] ?? '',
                    $citation['content'] ?? '',
                    $citation['contextContent'] ?? '',
                    $citation['sourceAuthorityLevel'] ?? '',
                    implode(' ', (array) ($citation['sourceAuthoritySignals'] ?? [])),
                    implode(' ', (array) ($citation['supportSignals'] ?? [])),
                ]))),
            ])
            ->all();

        foreach ($this->answerSentences($answer) as $sentence) {
            $markers = $this->citationMarkersInSentence($sentence, $citations);
            $isRiskyClaim = $this->isRiskyLegalClaim($sentence);

            if ($isRiskyClaim) {
                $riskyClaimCount++;
            }

            if (!$markers && $isRiskyClaim) {
                $markers = $this->inferredSupportMarkers($sentence, $citationTexts);
            }

            if (!$markers && $isRiskyClaim) {
                $issues[] = 'unsupported_claim';
                $unsupportedClaims[] = $this->auditSentenceExcerpt($sentence);

                continue;
            }

            if (!$markers) {
                continue;
            }

            $sentenceTokens = $this->supportTokens($sentence);
            $hasTextSupport = collect($markers)->contains(function (int $marker) use ($citationTexts, $sentenceTokens): bool {
                $sourceText = $citationTexts[$marker] ?? '';

                return collect($sentenceTokens)
                    ->filter(fn (string $token): bool => str_contains($sourceText, $token))
                    ->count() >= min(2, count($sentenceTokens));
            });
            $hasStrongCitation = collect($markers)->contains(fn (int $marker): bool => ($citations[$marker - 1]['supportLevel'] ?? 'contextual') !== 'contextual');
            $hasAuthoritativeCitation = collect($markers)->contains(fn (int $marker): bool => in_array($citations[$marker - 1]['sourceAuthorityLevel'] ?? null, ['official_current', 'current_corpus'], true));

            if (!$hasTextSupport && $isRiskyClaim) {
                $issues[] = 'weak_sentence_support';
                $weaklySupportedClaims[] = $this->auditSentenceExcerpt($sentence);
            } elseif ($isRiskyClaim) {
                $supportedRiskyClaimCount++;
            }

            if (!$hasStrongCitation && $this->isAuthorityClaim($sentence)) {
                $issues[] = 'contextual_authority';
                $weaklySupportedClaims[] = $this->auditSentenceExcerpt($sentence);
            }

            if (!$hasAuthoritativeCitation && $this->isAuthorityClaim($sentence)) {
                $issues[] = 'weak_source_authority';
                $weaklySupportedClaims[] = $this->auditSentenceExcerpt($sentence);
            }
        }

        $warnings = array_values(array_unique($issues));

        return [
            'status' => $this->supportAuditStatus($warnings, $riskyClaimCount, $supportedRiskyClaimCount),
            'language' => $language,
            'warnings' => $warnings,
            'citationCoverage' => [
                'riskyClaimCount' => $riskyClaimCount,
                'supportedRiskyClaimCount' => $supportedRiskyClaimCount,
                'unsupportedRiskyClaimCount' => count(array_unique($unsupportedClaims)),
                'weaklySupportedRiskyClaimCount' => count(array_unique($weaklySupportedClaims)),
            ],
            'unsupportedClaims' => array_values(array_unique(array_slice($unsupportedClaims, 0, 8))),
            'weaklySupportedClaims' => array_values(array_unique(array_slice($weaklySupportedClaims, 0, 8))),
            'citationAudits' => $this->citationAudits($citations),
        ];
    }

    private function supportAuditStatus(array $warnings, int $riskyClaimCount, int $supportedRiskyClaimCount): string
    {
        if (in_array('no_citations', $warnings, true) || in_array('unsupported_claim', $warnings, true)) {
            return 'insufficient_sources';
        }

        if ($warnings || ($riskyClaimCount > 0 && $supportedRiskyClaimCount < $riskyClaimCount)) {
            return 'partial_sources';
        }

        return 'strong_sources';
    }

    private function citationAudits(array $citations): array
    {
        return collect($citations)
            ->map(fn (array $citation, int $index): array => [
                'marker' => $index + 1,
                'articleNumber' => $citation['articleNumber'] ?? null,
                'documentTitle' => $citation['documentTitle'] ?? null,
                'supportLevel' => $citation['supportLevel'] ?? null,
                'supportSignals' => array_values(array_slice((array) ($citation['supportSignals'] ?? []), 0, 8)),
                'sourceAuthorityLevel' => $citation['sourceAuthorityLevel'] ?? null,
                'sourceAuthoritySignals' => array_values(array_slice((array) ($citation['sourceAuthoritySignals'] ?? []), 0, 8)),
                'contextScope' => $citation['contextScope'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function answerSentences(string $answer): array
    {
        return collect(preg_split('/(?<=[.!?])\s+/', $answer) ?: [])
            ->map(fn (string $sentence): string => trim($sentence))
            ->filter(fn (string $sentence): bool => Str::length($sentence) >= 25)
            ->values()
            ->all();
    }

    private function citationMarkersInSentence(string $sentence, array $citations): array
    {
        preg_match_all('/\[(\d+)\]/', $sentence, $matches);
        $citationCount = count($citations);

        $markers = collect($matches[1] ?? [])
            ->map(fn (string $marker): int => (int) $marker)
            ->filter(fn (int $marker): bool => $marker >= 1 && $marker <= $citationCount)
            ->unique()
            ->values();

        $normalizedSentence = $this->normalizeText($sentence);
        foreach ($citations as $index => $citation) {
            $articleNumber = $this->normalizeText($citation['articleNumber'] ?? '');

            if ($articleNumber !== '' && str_contains($normalizedSentence, $articleNumber)) {
                $markers->push($index + 1);
            }
        }

        return $markers->unique()->values()->all();
    }

    private function supportTokens(string $text): array
    {
        $stopWords = [
            'article', 'code', 'from', 'avec', 'dans', 'pour', 'that', 'this', 'the', 'and', 'les', 'des',
            'une', 'sur', 'aux', 'son', 'ses', 'est', 'sont', 'peut', 'doit', 'law', 'legal',
        ];

        return collect(preg_split('/\s+/', $this->normalizeText($text)) ?: [])
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => Str::length($token) >= 5 && !is_numeric($token) && !in_array($token, $stopWords, true))
            ->flatMap(fn (string $token): array => [$token, ...$this->bilingualSupportTokens($token)])
            ->unique()
            ->take(14)
            ->values()
            ->all();
    }

    private function inferredSupportMarkers(string $sentence, array $citationTexts): array
    {
        $sentenceTokens = $this->supportTokens($sentence);

        if (!$sentenceTokens) {
            return [];
        }

        return collect($citationTexts)
            ->mapWithKeys(function (string $sourceText, int $marker) use ($sentenceTokens): array {
                $matchCount = collect($sentenceTokens)
                    ->filter(fn (string $token): bool => str_contains($sourceText, $token))
                    ->count();

                return [$marker => $matchCount];
            })
            ->filter(fn (int $matchCount): bool => $matchCount >= min(2, count($sentenceTokens)))
            ->sortDesc()
            ->keys()
            ->take(2)
            ->values()
            ->all();
    }

    private function bilingualSupportTokens(string $token): array
    {
        return [
            'ownership' => ['propriete'],
            'acquires' => ['acquiert'],
            'acquire' => ['acquiert'],
            'perfected' => ['parfaite', 'parfait'],
            'consent' => ['consentement'],
            'delivery' => ['delivrance'],
            'delivered' => ['delivrance'],
            'seller' => ['vendeur'],
            'buyer' => ['acheteur'],
            'price' => ['prix'],
            'thing' => ['chose'],
            'sold' => ['vendue', 'vente'],
            'termination' => ['licenciement'],
            'terminate' => ['licenciement'],
            'terminated' => ['licenciement'],
            'dismissal' => ['licenciement'],
            'indemnity' => ['indemnite'],
            'compensation' => ['indemnite', 'dommages'],
            'employment' => ['emploi', 'travail', 'salarie'],
            'months' => ['mois'],
            'coownership' => ['copropriete'],
            'co-ownership' => ['copropriete'],
            'shared' => ['commun', 'communes'],
            'property' => ['immeuble', 'propriete'],
            'tenant' => ['locataire'],
            'landlord' => ['bailleur'],
            'recover' => ['recouvrement', 'recouvrer'],
            'recovery' => ['recouvrement'],
            'unpaid' => ['impaye', 'impayes'],
            'rent' => ['loyer', 'loyers'],
            'action' => ['action', 'demande', 'tribunal'],
            'procedure' => ['procedure', 'conditions'],
            'renting' => ['loue', 'louee', 'location'],
            'reason' => ['motif'],
            'justified' => ['justifie', 'motif'],
            'unjustified' => ['abusif', 'motif'],
            'valid' => ['valable'],
            'conduct' => ['conduite'],
            'business' => ['entreprise'],
            'employer' => ['employeur'],
            'employee' => ['salarie'],
            'misconduct' => ['faute'],
            'procedure' => ['procedure'],
            'complaint' => ['plainte'],
            'theft' => ['vol'],
            'court' => ['tribunal', 'juge'],
            'judge' => ['juge'],
            'children' => ['enfants'],
            'child' => ['enfant'],
            'support' => ['pension', 'entretien'],
            'alimony' => ['pension'],
            'education' => ['education'],
            'insolvent' => ['insolvable'],
            'insolvency' => ['insolvabilite'],
            'parent' => ['parent'],
            'parents' => ['parents'],
            'company' => ['societe'],
            'companies' => ['societes'],
            'registered' => ['immatricule', 'immatriculation'],
            'registration' => ['immatriculation'],
            'registry' => ['registre'],
            'commercial' => ['commerce', 'commerciale'],
            'activity' => ['activite'],
            'activities' => ['activites'],
            'territory' => ['territoire'],
            'foreign' => ['etrangeres', 'etranger'],
            'legal' => ['morales', 'juridiques'],
            'entities' => ['personnes'],
        ][$token] ?? [];
    }

    private function isRiskyLegalClaim(string $sentence): bool
    {
        $normalized = $this->normalizeText($sentence);

        if ($this->isSufficiencyOrFactSentence($normalized)) {
            return false;
        }

        if (
            str_contains($sentence, '?')
            || $this->isSectionHeadingSentence($normalized)
            || $this->isSourceIntroSentence($normalized)
            || $this->isGenericPracticalIntakeSentence($normalized)
            || $this->isPartyArgumentSentence($normalized)
        ) {
            return false;
        }

        return (bool) preg_match('/\b(must|shall|required|requires|deadline|days|court|judge|procedure|remedy|compensation|damages|indemnity|dismissal|termination|liability|ownership|valid|invalid|criminal|penalty|file|lawsuit|doit|obligatoire|exige|delai|jours|tribunal|juge|procedure|recours|indemnite|dommages|licenciement|responsabilite|propriete|valable|penal|peine|plainte|action)\b/u', $normalized);
    }

    private function isAuthorityClaim(string $sentence): bool
    {
        return (bool) preg_match('/\b(article|law|code|court|legal rule|requires|must|doit|loi|regle|tribunal|exige|oblige|prevoit|dispose)\b/u', $this->normalizeText($sentence));
    }

    private function isSufficiencyOrFactSentence(string $normalizedSentence): bool
    {
        if (preg_match('/\b(additional information|more information|information would be needed|needed to fully assess|fully assess|cannot fully assess)\b/u', $normalizedSentence)) {
            return true;
        }

        return (bool) preg_match('/\b(source|sources|insufficient|insuffisantes|missing|manquant|unresolved|non tranche|facts|faits|evidence|preuve|preuves|documents|question|limites|limits|citation verification|verification des citations)\b/u', $normalizedSentence);
    }

    private function isSectionHeadingSentence(string $normalizedSentence): bool
    {
        return (bool) preg_match('/^(a|b|c|d|e|f|g|h)\s+(important facts|faits importants|legal questions|questions juridiques|applicable articles|articles applicables|fact analysis|analyse des faits|arguments|important evidence|preuves importantes|probable conclusion|conclusion probable|limits|limites)\b/u', $normalizedSentence);
    }

    private function isSourceIntroSentence(string $normalizedSentence): bool
    {
        return (bool) preg_match('/\b(relevant moroccan law found|source juridique marocaine trouvee|legal context from the indexed corpus|contexte juridique dans le corpus indexe)\b/u', $normalizedSentence);
    }

    private function isGenericPracticalIntakeSentence(string $normalizedSentence): bool
    {
        return (bool) preg_match('/\b(report it quickly|signalez rapidement|preserve evidence|conservez les preuves|keep copies|gardez des copies|write down witnesses|notez les temoins|block bank cards|bloquez les cartes|timeline|chronologie|receipts|serial numbers|numeros de serie|proof of ownership|preuve de propriete)\b/u', $normalizedSentence);
    }

    private function isPartyArgumentSentence(string $normalizedSentence): bool
    {
        return (bool) preg_match('/\b(the employer may argue|the employee may argue|the tenant may argue|the landlord may argue|le salarie peut soutenir|l employeur peut soutenir|le locataire peut soutenir|le bailleur peut soutenir)\b/u', $normalizedSentence);
    }

    private function alreadyContainsSourceWarning(string $answer): bool
    {
        return (bool) preg_match('/\b(sources insuffisantes|source sufficiency|retrieved excerpts do not|extraits ne permettent pas)\b/i', $answer);
    }

    private function sourceSufficiencyWarning(array $issues, string $language): string
    {
        $authorityIssue = in_array('weak_source_authority', $issues, true);

        if ($language === 'fr') {
            return $authorityIssue
                ? 'Verification des citations: certaines conclusions doivent etre traitees prudemment, car elles s appuient sur des sources faibles, anciennes, legacy ou non explicitement officielles. Pour un usage professionnel, verifiez les textes complets, la version en vigueur et les sources applicables avant de conclure.'
                : 'Verification des citations: certaines conclusions doivent etre traitees prudemment, car les extraits cites ne soutiennent pas explicitement chaque procedure, delai, recours ou consequence mentionnee. Pour un usage professionnel, verifiez les textes complets et les sources applicables avant de conclure.';
        }

        return $authorityIssue
            ? 'Citation verification: treat some conclusions cautiously because they rely on weak, old, legacy, or not-explicitly-official sources. For professional use, verify the full texts, current version, and applicable sources before relying on the conclusion.'
            : 'Citation verification: treat some conclusions cautiously because the cited excerpts do not explicitly support every procedure, deadline, remedy, or consequence mentioned. For professional use, verify the full texts and applicable sources before relying on the conclusion.';
    }

    private function auditSentenceExcerpt(string $sentence): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $sentence) ?? $sentence), 240, '');
    }

    private function removeUnsupportedUncitedSentences(string $answer, array $unsupportedClaims): string
    {
        if (!$unsupportedClaims) {
            return $answer;
        }

        if (preg_match('/\b[A-H]\.\s/u', $answer)) {
            return $answer;
        }

        $unsupported = collect($unsupportedClaims)->map(fn (string $claim): string => $this->normalizeText($claim))->filter()->all();
        $sentences = $this->answerSentences($answer);
        $kept = collect($sentences)
            ->reject(function (string $sentence) use ($unsupported): bool {
                $normalized = $this->normalizeText($this->auditSentenceExcerpt($sentence));

                return in_array($normalized, $unsupported, true) && $this->isHighRiskUnsupportedClaim($sentence);
            })
            ->values()
            ->all();

        if (!$kept || count($kept) === count($sentences)) {
            return $answer;
        }

        return implode(' ', $kept);
    }

    private function normalizeText(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[^\p{L}\p{N}\s\[\]-]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function isHighRiskUnsupportedClaim(string $sentence): bool
    {
        return (bool) preg_match('/\b(deadline|days|court|judge|procedure|remedy|compensation|damages|indemnity|penalty|file|lawsuit|delai|jours|tribunal|juge|procedure|recours|indemnite|dommages|peine|plainte|action)\b/u', $this->normalizeText($sentence));
    }
}
