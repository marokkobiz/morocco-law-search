<?php

namespace Tests\Feature;

use App\Jobs\Adala\BuildCorpusJob;
use App\Jobs\Adala\DiscoverAdalaDocumentsJob;
use App\Jobs\Adala\DownloadPdfJob;
use App\Jobs\Adala\GenerateEmbeddingsJob;
use App\Jobs\Adala\ImportPdfJob;
use App\Jobs\Adala\SyncQdrantJob;
use App\Models\AdalaCrawlRun;
use App\Models\AdalaDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RetryFailedAdalaDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function failedDocument(AdalaCrawlRun $run, string $url, int $retryCount): AdalaDocument
    {
        return AdalaDocument::query()->create([
            'adala_crawl_run_id' => $run->id,
            'source_url' => $url,
            'normalized_url' => $url,
            'url_hash' => hash('sha256', $url),
            'title' => 'Failed doc',
            'status' => AdalaDocument::STATUS_FAILED,
            'retry_count' => $retryCount,
            'error_message' => 'boom',
            'discovered_at' => now(),
        ]);
    }

    public function test_it_requeues_failed_documents_under_the_retry_limit(): void
    {
        Bus::fake();
        config(['adala.processing.max_retries' => 5]);

        $run = AdalaCrawlRun::query()->create([
            'status' => AdalaCrawlRun::STATUS_RUNNING,
            'seed_urls' => ['https://adala.justice.gov.ma/fr'],
            'started_at' => now(),
        ]);

        $document = $this->failedDocument($run, 'https://adala.justice.gov.ma/api/uploads/2026/06/19/a-1.pdf', 1);

        $this->artisan('adala:retry-failed', ['--run' => $run->id])
            ->assertSuccessful();

        $this->assertSame(AdalaDocument::STATUS_DISCOVERED, $document->fresh()->status);
        $this->assertNull($document->fresh()->error_message);

        Bus::assertChained([
            DownloadPdfJob::class,
            ImportPdfJob::class,
            BuildCorpusJob::class,
            GenerateEmbeddingsJob::class,
            SyncQdrantJob::class,
            DiscoverAdalaDocumentsJob::class,
        ]);
    }

    public function test_it_skips_documents_at_the_retry_limit_unless_reset(): void
    {
        Bus::fake();
        config(['adala.processing.max_retries' => 5]);

        $run = AdalaCrawlRun::query()->create([
            'status' => AdalaCrawlRun::STATUS_RUNNING,
            'seed_urls' => ['https://adala.justice.gov.ma/fr'],
            'started_at' => now(),
        ]);

        $document = $this->failedDocument($run, 'https://adala.justice.gov.ma/api/uploads/2026/06/19/b-2.pdf', 5);

        $this->artisan('adala:retry-failed', ['--run' => $run->id])
            ->assertSuccessful();

        $this->assertSame(AdalaDocument::STATUS_FAILED, $document->fresh()->status);

        $this->artisan('adala:retry-failed', ['--run' => $run->id, '--reset-retry-count' => true])
            ->assertSuccessful();

        $this->assertSame(AdalaDocument::STATUS_DISCOVERED, $document->fresh()->status);
        $this->assertSame(0, (int) $document->fresh()->retry_count);
    }
}
