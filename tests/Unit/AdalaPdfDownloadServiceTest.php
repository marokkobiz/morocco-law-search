<?php

namespace Tests\Unit;

use App\Services\Adala\AdalaPdfDownloadService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdalaPdfDownloadServiceTest extends TestCase
{
    public function test_it_retries_slow_downloads_and_validates_pdf_content(): void
    {
        config([
            'adala.download.max_retries' => 2,
            'adala.download.retry_backoff_seconds' => [0, 0],
            'adala.download.min_file_size_bytes' => 10,
            'adala.download.storage_directory' => 'testing/adala',
        ]);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new \Illuminate\Http\Client\ConnectionException('timeout');
            }

            return Http::response('%PDF-1.4 test content', 200);
        });

        $service = app(AdalaPdfDownloadService::class);
        $result = $service->download('https://adala.justice.gov.ma/api/uploads/sample.pdf');

        $this->assertSame(2, $attempts);
        $this->assertSame('%PDF-1.4 test content', file_get_contents(storage_path('app/'.$result['path'])));
        $this->assertTrue($service->validateExistingFile($result['path'], $result['checksum']));
    }
}
