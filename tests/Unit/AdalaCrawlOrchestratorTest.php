<?php

namespace Tests\Unit;

use App\Jobs\Adala\BuildCorpusJob;
use App\Jobs\Adala\DiscoverAdalaDocumentsJob;
use App\Jobs\Adala\DownloadPdfJob;
use App\Jobs\Adala\GenerateEmbeddingsJob;
use App\Jobs\Adala\ImportPdfJob;
use App\Jobs\Adala\SyncQdrantJob;
use App\Models\AdalaCrawlRun;
use App\Models\AdalaDocument;
use App\Services\Adala\AdalaCrawlOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AdalaCrawlOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_dispatches_pipeline_for_discovered_documents(): void
    {
        Bus::fake();

        $run = AdalaCrawlRun::query()->create([
            'status' => AdalaCrawlRun::STATUS_RUNNING,
            'seed_urls' => ['https://adala.justice.gov.ma'],
            'started_at' => now(),
        ]);

        $document = AdalaDocument::query()->create([
            'adala_crawl_run_id' => $run->id,
            'source_url' => 'https://adala.justice.gov.ma/api/uploads/2026/06/19/sample-123.pdf',
            'normalized_url' => 'https://adala.justice.gov.ma/api/uploads/2026/06/19/sample-123.pdf',
            'url_hash' => hash('sha256', 'https://adala.justice.gov.ma/api/uploads/2026/06/19/sample-123.pdf'),
            'title' => 'Sample Decision',
            'status' => AdalaDocument::STATUS_DISCOVERED,
            'discovered_at' => now(),
        ]);

        app(AdalaCrawlOrchestrator::class)->resume($run->id);

        Bus::assertChained([
            DownloadPdfJob::class,
            ImportPdfJob::class,
            BuildCorpusJob::class,
            GenerateEmbeddingsJob::class,
            SyncQdrantJob::class,
            DiscoverAdalaDocumentsJob::class,
        ]);
    }
}
