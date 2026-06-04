<?php

namespace App\Services;

use Illuminate\Support\Str;

class LegalDomainClassifier
{
    private const DOMAIN_PROFILES = [
        'labor' => [
            'terms' => ['travail', 'contrat de travail', 'salarie', 'employeur', 'licenciement', 'preavis', 'salaire', 'indemnite', 'faute grave', 'employee', 'employer', 'dismissal', 'termination', 'salary', 'wage'],
            'subdomains' => [
                'dismissal' => ['licenciement', 'faute grave', 'motif valable', 'preavis', 'dismissal', 'termination', 'fired'],
                'wages' => ['salaire', 'paie', 'remuneration', 'wage', 'salary'],
                'discipline' => ['sanction', 'disciplinaire', 'faute', 'procedure disciplinaire'],
            ],
        ],
        'criminal' => [
            'terms' => ['penal', 'crime', 'infraction', 'vol', 'soustraction', 'homicide', 'coups', 'blessures', 'agression', 'arme', 'prison', 'theft', 'robbery', 'assault', 'criminal'],
            'subdomains' => [
                'theft' => ['vol', 'soustraction frauduleuse', 'theft', 'robbery', 'stolen'],
                'violence' => ['coups', 'blessures', 'agression', 'homicide', 'arme', 'violence', 'assault', 'shot'],
                'procedure' => ['plainte', 'poursuite', 'procureur', 'tribunal penal'],
            ],
        ],
        'civil_obligations_contracts' => [
            'terms' => ['obligations', 'contrats', 'contrat', 'convention', 'vente', 'acheteur', 'acquereur', 'vendeur', 'prix', 'chose vendue', 'delivrance', 'paiement', 'propriete', 'transfert de propriete', 'possession', 'obligations du vendeur', 'obligations de l acheteur', 'ayants cause', 'responsabilite civile', 'dommages interets', 'contract', 'sale', 'buyer', 'seller', 'price', 'delivery', 'ownership', 'possession'],
            'subdomains' => [
                'sale' => ['vente', 'contrat de vente', 'acheteur', 'acquereur', 'vendeur', 'prix', 'chose vendue', 'delivrance', 'paiement', 'propriete', 'transfert de propriete', 'possession', 'tradition reelle', 'obligations du vendeur', 'ayants cause', 'sale', 'buyer', 'seller', 'delivery', 'ownership'],
                'contract_performance' => ['execution', 'bonne foi', 'resolution', 'resiliation', 'obligation', 'inexecution', 'dommages interets'],
                'civil_liability' => ['responsabilite', 'prejudice', 'dommage', 'reparation'],
            ],
        ],
        'succession_inheritance' => [
            'terms' => ['succession', 'heritage', 'heritier', 'heritiers', 'ayants droit', 'ayants cause', 'testament', 'legs', 'inheritance', 'heirs', 'estate'],
            'subdomains' => [
                'heirs' => ['heritier', 'heritiers', 'ayants droit', 'ayants cause', 'heirs'],
                'estate' => ['succession', 'heritage', 'masse successorale', 'estate'],
                'will' => ['testament', 'legs', 'will'],
            ],
        ],
        'family_marriage_divorce' => [
            'terms' => ['famille', 'mariage', 'divorce', 'garde', 'pension', 'nafaqa', 'filiation', 'epoux', 'epouse', 'marriage', 'divorce', 'custody', 'alimony'],
            'subdomains' => [
                'marriage' => ['mariage', 'epoux', 'epouse', 'dot', 'marriage'],
                'divorce' => ['divorce', 'repudiation', 'separation'],
                'custody_support' => ['garde', 'pension', 'nafaqa', 'custody', 'alimony'],
            ],
        ],
        'real_estate_rent' => [
            'terms' => ['immobilier', 'foncier', 'propriete fonciere', 'titre foncier', 'bail', 'loyer', 'locataire', 'bailleur', 'expulsion', 'copropriete', 'rent', 'tenant', 'landlord', 'lease', 'real estate'],
            'subdomains' => [
                'rent' => ['bail', 'loyer', 'locataire', 'bailleur', 'rent', 'tenant', 'landlord', 'lease'],
                'land_title' => ['titre foncier', 'propriete fonciere', 'immatriculation fonciere', 'land title'],
                'coownership' => ['copropriete', 'syndic'],
            ],
        ],
        'commercial_company' => [
            'terms' => ['commerce', 'commercial', 'societe', 'sarl', 'sa', 'actionnaire', 'associe', 'registre de commerce', 'gerant', 'company', 'corporate', 'shareholder'],
            'subdomains' => [
                'company' => ['societe', 'sarl', 'sa', 'actionnaire', 'associe', 'gerant', 'company', 'shareholder'],
                'merchant' => ['commercant', 'acte de commerce', 'fonds de commerce'],
                'registration' => ['registre de commerce', 'immatriculation commerciale'],
            ],
        ],
        'tax' => [
            'terms' => ['impot', 'impots', 'taxe', 'fiscal', 'fiscalite', 'tva', 'douane', 'tax', 'vat'],
            'subdomains' => [
                'vat' => ['tva', 'vat'],
                'income_tax' => ['ir', 'impot sur le revenu'],
                'corporate_tax' => ['is', 'impot sur les societes'],
                'customs' => ['douane', 'importation', 'exportation'],
            ],
        ],
        'administrative_urbanism' => [
            'terms' => ['administratif', 'administration', 'autorisation', 'permis', 'urbanisme', 'commune', 'collectivite', 'expropriation', 'marche public', 'public procurement', 'permit', 'license'],
            'subdomains' => [
                'urbanism' => ['urbanisme', 'permis de construire', 'lotissement', 'construction'],
                'public_procurement' => ['marche public', 'commande publique', 'appel d offres', 'public procurement'],
                'administrative_authorization' => ['autorisation', 'permis', 'license', 'administration'],
            ],
        ],
    ];

    private const CATEGORY_ALIASES = [
        'civil' => 'civil_obligations_contracts',
        'contracts' => 'civil_obligations_contracts',
        'contract' => 'civil_obligations_contracts',
        'labor' => 'labor',
        'travail' => 'labor',
        'criminal' => 'criminal',
        'penal' => 'criminal',
        'family' => 'family_marriage_divorce',
        'real-estate' => 'real_estate_rent',
        'real_estate' => 'real_estate_rent',
        'commercial' => 'commercial_company',
        'commerce' => 'commercial_company',
        'business' => 'commercial_company',
        'companies' => 'commercial_company',
        'company' => 'commercial_company',
        'tax' => 'tax',
        'fiscal' => 'tax',
        'administrative' => 'administrative_urbanism',
        'urbanism' => 'administrative_urbanism',
        'official-bulletin' => 'official-bulletin',
    ];

    public function classifyQuery(string $query): array
    {
        return $this->classify([$query]);
    }

    public function classifyDocument(array $context): array
    {
        return $this->classify([
            $context['documentTitle'] ?? $context['document_title'] ?? '',
            $context['sourceCategory'] ?? $context['source_category'] ?? $context['category'] ?? '',
            $context['sourceName'] ?? $context['source_name'] ?? '',
            $context['lawReference'] ?? $context['law_reference'] ?? '',
            $context['headings'] ?? [],
            $context['text'] ?? '',
        ], $context['tags'] ?? []);
    }

    public function classifyArticle(array $context, ?array $documentTaxonomy = null): array
    {
        return $this->classify([
            $context['documentTitle'] ?? $context['document_title'] ?? '',
            $context['sourceCategory'] ?? $context['source_category'] ?? $context['category'] ?? '',
            $context['chapterHeading'] ?? $context['chapter_heading'] ?? '',
            $context['sectionHeading'] ?? $context['section_heading'] ?? '',
            $context['articleTitle'] ?? $context['article_title'] ?? '',
            $context['articleText'] ?? $context['article_text'] ?? $context['content'] ?? '',
        ], $context['tags'] ?? [], $documentTaxonomy);
    }

    public function classifyChunk(array $context, ?array $articleTaxonomy = null): array
    {
        return $this->classify([
            $context['documentTitle'] ?? $context['document_title'] ?? '',
            $context['articleTitle'] ?? $context['article_title'] ?? '',
            $context['chunkText'] ?? $context['chunk_text'] ?? $context['content'] ?? '',
        ], $context['tags'] ?? [], $articleTaxonomy);
    }

    public function conceptTermsForQuery(string $query, int $limit = 14): array
    {
        return $this->conceptTermsForTaxonomy($this->classifyQuery($query), $limit);
    }

    public function conceptTermsForTaxonomy(?array $taxonomy, int $limit = 14): array
    {
        $domain = $taxonomy['domain'] ?? null;

        if (!$domain || !isset(self::DOMAIN_PROFILES[$domain])) {
            return [];
        }

        $domains = collect($taxonomy['scores'] ?? [])
            ->sortDesc()
            ->keys()
            ->filter(fn (string $candidate): bool => isset(self::DOMAIN_PROFILES[$candidate]))
            ->prepend($domain)
            ->unique()
            ->take(2);
        $terms = collect();

        foreach ($domains as $candidateDomain) {
            $profile = self::DOMAIN_PROFILES[$candidateDomain];
            $subdomain = $candidateDomain === $domain
                ? ($taxonomy['subdomain'] ?? null)
                : collect($taxonomy['subdomainScores'][$candidateDomain] ?? [])->sortDesc()->keys()->first();

            if ($subdomain && isset($profile['subdomains'][$subdomain])) {
                $terms = $terms->concat($profile['subdomains'][$subdomain]);
            }

            $terms = $terms->concat($profile['terms']);
        }

        return $terms
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => $term !== '')
            ->unique(fn (string $term): string => $this->normalize($term))
            ->take($limit)
            ->values()
            ->all();
    }

    private function classify(array $texts, array|string|null $seedTags = [], ?array $fallback = null): array
    {
        $text = $this->normalize(implode(' ', array_map(
            fn (mixed $value): string => is_array($value) ? implode(' ', $value) : (string) $value,
            $texts
        )));
        $tags = collect(is_array($seedTags) ? $seedTags : [$seedTags])
            ->map(fn (mixed $tag): string => $this->tag((string) $tag))
            ->filter()
            ->values();
        $domainScores = [];
        $subdomainScores = [];

        foreach (self::CATEGORY_ALIASES as $alias => $domain) {
            if ($this->matches($text, $alias)) {
                $domainScores[$domain] = ($domainScores[$domain] ?? 0) + 6;
                $tags->push($this->tag($alias));
            }
        }

        foreach (self::DOMAIN_PROFILES as $domain => $profile) {
            foreach ($profile['terms'] as $term) {
                if ($this->matches($text, $term)) {
                    $domainScores[$domain] = ($domainScores[$domain] ?? 0) + $this->termWeight($term);
                    $tags->push($this->tag($term));
                }
            }

            foreach ($profile['subdomains'] as $subdomain => $terms) {
                foreach ($terms as $term) {
                    if ($this->matches($text, $term)) {
                        $subdomainScores[$domain][$subdomain] = ($subdomainScores[$domain][$subdomain] ?? 0) + $this->termWeight($term);
                        $tags->push($this->tag($subdomain));
                    }
                }
            }
        }

        arsort($domainScores);
        $domain = array_key_first($domainScores) ?: ($fallback['domain'] ?? null);
        $subdomain = null;

        if ($domain && isset($subdomainScores[$domain])) {
            arsort($subdomainScores[$domain]);
            $subdomain = array_key_first($subdomainScores[$domain]);
        }

        $subdomain ??= $fallback['subdomain'] ?? null;

        return [
            'domain' => $domain,
            'subdomain' => $subdomain,
            'tags' => $tags
                ->merge(array_filter([$domain, $subdomain]))
                ->map(fn (string $tag): string => $this->tag($tag))
                ->filter()
                ->unique()
                ->take(30)
                ->values()
                ->all(),
            'scores' => $domainScores,
            'subdomainScores' => $subdomainScores,
        ];
    }

    private function matches(string $text, string $term): bool
    {
        $term = $this->normalize($term);

        if ($term === '') {
            return false;
        }

        if (str_contains($term, ' ')) {
            return str_contains($text, $term);
        }

        return (bool) preg_match('/\b'.preg_quote($term, '/').'\b/u', $text);
    }

    private function termWeight(string $term): int
    {
        return str_contains(trim($term), ' ') ? 4 : 2;
    }

    private function tag(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
