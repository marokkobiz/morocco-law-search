<?php

namespace App\Services;

use App\Models\ImportRun;
use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalSource;
use Illuminate\Support\Facades\DB;

class CorpusStatusService
{
    public const COVERAGE_WARNING = 'Coverage depends on indexed official sources.';

    public function status(): array
    {
        $latestImport = ImportRun::query()
            ->orderByDesc('finished_at')
            ->orderByDesc('started_at')
            ->first();
        $latestImportDate = $latestImport?->finished_at ?? $latestImport?->started_at;

        return [
            'totalSources' => LegalSource::query()->count(),
            'totalDocuments' => LegalDocument::query()->count(),
            'activeDocuments' => LegalDocument::query()->where('status', 'active')->count(),
            'totalVersions' => LegalDocumentVersion::query()->count(),
            'activeVersions' => LegalDocumentVersion::query()->where('status', 'active')->count(),
            'totalArticles' => LegalArticle::query()->count(),
            'activeArticles' => LegalArticle::query()->where('status', 'active')->count(),
            'totalChunks' => LegalChunk::query()->count(),
            'latestImportDate' => $latestImportDate?->toISOString(),
            'coverageBySource' => $this->coverageBySource(),
            'coverageByDomain' => $this->coverageByDomain(),
            'documentsByStatus' => $this->countByStatus('legal_documents'),
            'versionsByStatus' => $this->countByStatus('legal_document_versions'),
            'latestRuns' => $this->latestRuns(),
            'warning' => self::COVERAGE_WARNING,
        ];
    }

    private function coverageBySource(): array
    {
        return DB::table('legal_sources')
            ->leftJoin('legal_documents', 'legal_documents.legal_source_id', '=', 'legal_sources.id')
            ->leftJoin('legal_articles', function ($join): void {
                $join->on('legal_articles.legal_document_id', '=', 'legal_documents.id')
                    ->where('legal_articles.status', '=', 'active');
            })
            ->selectRaw('legal_sources.source_type as sourceType')
            ->selectRaw('COUNT(DISTINCT legal_sources.id) as sourceCount')
            ->selectRaw('COUNT(DISTINCT legal_documents.id) as documentCount')
            ->selectRaw('COUNT(DISTINCT legal_articles.id) as articleCount')
            ->groupBy('legal_sources.source_type')
            ->orderByDesc('articleCount')
            ->get()
            ->map(fn (object $row): array => [
                'sourceType' => $row->sourceType,
                'sourceCount' => (int) $row->sourceCount,
                'documentCount' => (int) $row->documentCount,
                'articleCount' => (int) $row->articleCount,
            ])
            ->values()
            ->all();
    }

    private function coverageByDomain(): array
    {
        return DB::table('legal_documents')
            ->leftJoin('legal_articles', function ($join): void {
                $join->on('legal_articles.legal_document_id', '=', 'legal_documents.id')
                    ->where('legal_articles.status', '=', 'active');
            })
            ->selectRaw("COALESCE(legal_documents.domain, 'general') as domain")
            ->selectRaw('COUNT(DISTINCT legal_documents.id) as documentCount')
            ->selectRaw('COUNT(DISTINCT legal_articles.id) as articleCount')
            ->groupBy(DB::raw("COALESCE(legal_documents.domain, 'general')"))
            ->orderByDesc('articleCount')
            ->get()
            ->map(fn (object $row): array => [
                'domain' => $row->domain,
                'documentCount' => (int) $row->documentCount,
                'articleCount' => (int) $row->articleCount,
            ])
            ->values()
            ->all();
    }

    private function countByStatus(string $table): array
    {
        return DB::table($table)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (object $row): array => [
                'status' => $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function latestRuns(): array
    {
        return ImportRun::query()
            ->orderByDesc('started_at')
            ->limit(5)
            ->get()
            ->map(fn (ImportRun $run): array => [
                'id' => $run->id,
                'importType' => $run->import_type,
                'sourceUrl' => $run->source_url,
                'sourceFilePath' => $run->source_file_path,
                'startedAt' => $run->started_at?->toISOString(),
                'finishedAt' => $run->finished_at?->toISOString(),
                'status' => $run->status,
                'documentsImported' => $run->documents_imported,
                'articlesExtracted' => $run->articles_extracted,
                'chunksCreated' => $run->chunks_created,
                'errorCount' => count($run->errors ?? []),
            ])
            ->values()
            ->all();
    }
}
