<?php

namespace App\Services\Adala;

use App\Models\AdalaCrawlRun;
use App\Models\AdalaDocument;
use Illuminate\Support\Carbon;

class AdalaCrawlStatisticsService
{
    public function forRun(?AdalaCrawlRun $run = null): array
    {
        $run ??= AdalaCrawlRun::query()->latest('id')->first();

        if (!$run) {
            return [
                'run' => null,
                'message' => 'No Adala crawl run found.',
            ];
        }

        $run->refresh();

        $statusCounts = AdalaDocument::query()
            ->where('adala_crawl_run_id', $run->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $pendingPages = $run->pages()->where('status', 'pending')->count();
        $totalPages = $run->pages()->count();
        $indexTotals = AdalaDocument::query()
            ->where('adala_crawl_run_id', $run->id)
            ->selectRaw('COALESCE(SUM(chunks_created),0) as chunks_created')
            ->selectRaw('COALESCE(SUM(chunks_embedded),0) as chunks_embedded')
            ->selectRaw('COALESCE(SUM(chunks_vectorized),0) as chunks_vectorized')
            ->first();
        $completed = (int) ($statusCounts[AdalaDocument::STATUS_COMPLETED] ?? 0);
        $failed = (int) ($statusCounts[AdalaDocument::STATUS_FAILED] ?? 0);
        $discovered = (int) $run->documents_discovered;
        $inProgress = max(0, $discovered - $completed - $failed);
        $elapsedSeconds = $run->started_at ? $run->started_at->diffInSeconds(now()) : 0;
        $ratePerHour = $elapsedSeconds > 0 ? round(($completed / $elapsedSeconds) * 3600, 2) : 0.0;
        $remaining = max(0, $discovered - $completed - $failed);
        $etaSeconds = ($ratePerHour > 0 && $remaining > 0)
            ? (int) round(($remaining / $ratePerHour) * 3600)
            : null;

        $avgDurationMs = AdalaDocument::query()
            ->where('adala_crawl_run_id', $run->id)
            ->where('status', AdalaDocument::STATUS_COMPLETED)
            ->whereNotNull('processing_duration_ms')
            ->avg('processing_duration_ms');

        $failures = AdalaDocument::query()
            ->where('adala_crawl_run_id', $run->id)
            ->where('status', AdalaDocument::STATUS_FAILED)
            ->orderByDesc('last_attempt_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'title',
                'source_url',
                'error_message',
                'retry_count',
                'last_attempt_at',
                'metadata',
            ])
            ->map(fn (AdalaDocument $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'source_url' => $document->source_url,
                'failed_step' => data_get($document->metadata, 'failed_step'),
                'error_message' => $document->error_message,
                'retry_count' => (int) $document->retry_count,
                'last_attempt_at' => $document->last_attempt_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'pages_crawled' => (int) $run->pages_crawled,
                'pages_discovered' => $totalPages,
                'pending_pages' => $pendingPages,
            ],
            'documents' => [
                'discovered' => $discovered,
                'completed' => $completed,
                'failed' => $failed,
                'in_progress' => $inProgress,
                'pending_discovery' => $pendingPages > 0 || $run->status === AdalaCrawlRun::STATUS_RUNNING,
                'by_status' => $statusCounts,
            ],
            'index' => [
                'urls_discovered' => $totalPages,
                'chunks_created' => (int) ($indexTotals->chunks_created ?? 0),
                'embeddings_generated' => (int) ($indexTotals->chunks_embedded ?? 0),
                'vectors_synced' => (int) ($indexTotals->chunks_vectorized ?? 0),
            ],
            'performance' => [
                'elapsed_seconds' => $elapsedSeconds,
                'completed_per_hour' => $ratePerHour,
                'avg_processing_ms' => $avgDurationMs ? (int) round($avgDurationMs) : null,
                'eta' => $etaSeconds ? Carbon::now()->addSeconds($etaSeconds)->toIso8601String() : null,
            ],
            'failures' => $failures,
        ];
    }
}
