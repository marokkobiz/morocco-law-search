<?php

namespace Tests\Unit;

use App\Models\AdalaCrawlRun;
use App\Models\AdalaDocument;
use App\Services\Adala\AdalaCrawlStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdalaCrawlStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_failure_reasons_in_run_statistics(): void
    {
        $run = AdalaCrawlRun::query()->create([
            'status' => AdalaCrawlRun::STATUS_RUNNING,
            'seed_urls' => ['https://adala.justice.gov.ma'],
            'started_at' => now(),
        ]);

        AdalaDocument::query()->create([
            'adala_crawl_run_id' => $run->id,
            'source_url' => 'https://adala.justice.gov.ma/api/uploads/sample.pdf',
            'normalized_url' => 'https://adala.justice.gov.ma/api/uploads/sample.pdf',
            'url_hash' => hash('sha256', 'https://adala.justice.gov.ma/api/uploads/sample.pdf'),
            'title' => 'Sample Convention',
            'status' => AdalaDocument::STATUS_FAILED,
            'error_message' => 'HTTP 504 while downloading sample.pdf',
            'retry_count' => 2,
            'last_attempt_at' => now(),
            'metadata' => ['failed_step' => 'download'],
            'discovered_at' => now(),
        ]);

        $stats = app(AdalaCrawlStatisticsService::class)->forRun($run);

        $this->assertCount(1, $stats['failures']);
        $this->assertSame('HTTP 504 while downloading sample.pdf', $stats['failures'][0]['error_message']);
        $this->assertSame('download', $stats['failures'][0]['failed_step']);
        $this->assertSame('Sample Convention', $stats['failures'][0]['title']);
    }
}
