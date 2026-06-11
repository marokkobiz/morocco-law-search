<?php

namespace App\Console\Commands;

use App\Services\SearchTextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildLegalSearchIndex extends Command
{
    protected $signature = 'legal-search:build-fts
        {--batch=500 : Rows per insert batch}';

    protected $description = 'Rebuild the SQLite FTS5 full-text index over the active legal corpus.';

    public function handle(SearchTextNormalizer $normalizer): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('The FTS index is only available on the sqlite driver.');

            return self::FAILURE;
        }

        $batchSize = max(50, (int) $this->option('batch'));

        DB::statement('PRAGMA busy_timeout = 60000');
        DB::statement('DELETE FROM legal_chunks_fts');

        $query = DB::table('legal_chunks')
            ->join('legal_articles', 'legal_articles.id', '=', 'legal_chunks.legal_article_id')
            ->join('legal_documents', 'legal_documents.id', '=', 'legal_articles.legal_document_id')
            ->join('legal_document_versions', function ($join): void {
                $join->on('legal_document_versions.id', '=', 'legal_chunks.legal_document_version_id')
                    ->on('legal_document_versions.legal_document_id', '=', 'legal_documents.id');
            })
            ->where('legal_documents.status', 'active')
            ->where('legal_document_versions.status', 'active')
            ->where('legal_articles.status', 'active')
            ->whereColumn('legal_documents.current_version_id', 'legal_document_versions.id')
            ->leftJoin('legal_sources', 'legal_sources.id', '=', 'legal_documents.legal_source_id')
            ->leftJoin('laws AS legacy_laws', 'legacy_laws.id', '=', 'legal_articles.legacy_law_id')
            ->select([
                'legal_chunks.id AS chunk_id',
                'legal_chunks.content',
                'legal_chunks.tags AS chunk_tags',
                'legal_chunks.domain AS chunk_domain',
                'legal_articles.article_title',
                'legal_articles.article_number',
                'legal_articles.tags AS article_tags',
                'legal_articles.domain AS article_domain',
                'legal_documents.document_title',
                'legal_documents.law_reference',
                'legal_documents.tags AS document_tags',
                'legal_documents.domain AS document_domain',
                'legal_documents.document_type',
                'legal_sources.source_type',
                'legacy_laws.category AS legacy_category',
            ])
            ->orderBy('legal_chunks.id');

        $total = 0;
        $buffer = [];

        foreach ($query->cursor() as $row) {
            $buffer[] = [
                'chunk_id' => (int) $row->chunk_id,
                'chat_only' => $this->isChatOnly($row) ? 1 : 0,
                'document_title' => $normalizer->normalize(implode(' ', array_filter([
                    (string) $row->document_title,
                    (string) $row->law_reference,
                ]))),
                'article_title' => $normalizer->normalize(implode(' ', array_filter([
                    (string) $row->article_title,
                    (string) $row->article_number,
                ]))),
                'content' => $normalizer->normalize((string) $row->content),
                'tags' => $normalizer->normalize(implode(' ', array_filter([
                    $this->flattenTags($row->chunk_tags),
                    $this->flattenTags($row->article_tags),
                    $this->flattenTags($row->document_tags),
                    (string) $row->chunk_domain,
                    (string) $row->article_domain,
                    (string) $row->document_domain,
                ]))),
            ];

            if (count($buffer) >= $batchSize) {
                $total += $this->flush($buffer);
                $buffer = [];
            }
        }

        $total += $this->flush($buffer);

        DB::statement("INSERT INTO legal_chunks_fts(legal_chunks_fts) VALUES('optimize')");

        $this->info("Indexed {$total} chunks into legal_chunks_fts.");

        return self::SUCCESS;
    }

    private function flush(array $rows): int
    {
        if (!$rows) {
            return 0;
        }

        DB::table('legal_chunks_fts')->insert($rows);

        return count($rows);
    }

    /**
     * Mirrors LawSearchService chat-only source detection: Bulletin Officiel
     * material is reachable from chat but excluded from regular search.
     */
    private function isChatOnly(object $row): bool
    {
        $chatOnlyCategories = ['official-bulletin', 'official_bulletin', 'official bulletin', 'bulletins officiels'];
        $chatOnlyTypes = ['bo', 'official-bulletin', 'official_bulletin', 'official bulletin'];

        foreach ([
            $row->legacy_category ?? null,
            $row->document_domain ?? null,
            $row->article_domain ?? null,
            $row->chunk_domain ?? null,
        ] as $value) {
            if (is_string($value) && in_array(mb_strtolower(trim($value)), $chatOnlyCategories, true)) {
                return true;
            }
        }

        foreach ([
            $row->source_type ?? null,
            $row->document_type ?? null,
        ] as $value) {
            if (is_string($value) && in_array(mb_strtolower(trim($value)), $chatOnlyTypes, true)) {
                return true;
            }
        }

        return false;
    }

    private function flattenTags(mixed $tags): string
    {
        if (!is_string($tags) || trim($tags) === '') {
            return '';
        }

        $decoded = json_decode($tags, true);

        if (is_array($decoded)) {
            return implode(' ', array_filter(array_map(
                fn (mixed $tag): string => is_string($tag) ? $tag : '',
                $decoded
            )));
        }

        return $tags;
    }
}
