<?php

namespace App\Services\Adala;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AdalaPdfDownloadService
{
    public function __construct(private readonly AdalaUrlNormalizer $urls)
    {
    }

    /**
     * @return array{path: string, size: int, checksum: string}
     */
    public function download(string $sourceUrl, ?string $targetRelativePath = null): array
    {
        $sourceUrl = $this->urls->normalize($sourceUrl);
        $maxRetries = max(1, (int) config('adala.download.max_retries', 5));
        $backoff = (array) config('adala.download.retry_backoff_seconds', [10, 30, 60, 120]);
        $lastError = 'Unknown download error';

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->attemptDownload($sourceUrl, $targetRelativePath);
            } catch (Throwable $error) {
                $lastError = $error->getMessage();

                if ($attempt >= $maxRetries) {
                    break;
                }

                $wait = (int) ($backoff[$attempt - 1] ?? end($backoff));
                sleep(max(1, $wait));
            }
        }

        throw new RuntimeException($lastError);
    }

    public function validateExistingFile(string $relativePath, ?string $expectedChecksum = null): bool
    {
        $absolute = storage_path('app/'.$relativePath);

        if (!is_file($absolute)) {
            return false;
        }

        $size = filesize($absolute);

        if ($size === false || $size < (int) config('adala.download.min_file_size_bytes', 512)) {
            return false;
        }

        $contents = file_get_contents($absolute);

        if ($contents === false || !$this->isValidPdf($contents)) {
            return false;
        }

        if ($expectedChecksum !== null && hash('sha256', $contents) !== $expectedChecksum) {
            return false;
        }

        return true;
    }

    /**
     * @return array{path: string, size: int, checksum: string}
     */
    private function attemptDownload(string $sourceUrl, ?string $targetRelativePath): array
    {
        $connectTimeout = max(1, (int) config('adala.download.connect_timeout_seconds', 30));
        $readTimeout = max(1, (int) config('adala.download.read_timeout_seconds', 600));

        $response = Http::withOptions([
            'connect_timeout' => $connectTimeout,
            'timeout' => $readTimeout,
        ])
            ->withHeaders([
                'User-Agent' => (string) config('adala.crawl.user_agent'),
                'Accept' => 'application/pdf,*/*',
            ])
            ->get($sourceUrl);

        if (!$response->successful()) {
            throw new RuntimeException("HTTP {$response->status()} while downloading {$sourceUrl}");
        }

        $body = $response->body();
        $size = strlen($body);
        $minSize = (int) config('adala.download.min_file_size_bytes', 512);

        if ($size < $minSize) {
            throw new RuntimeException("Partial download for {$sourceUrl}: only {$size} bytes received.");
        }

        if (!$this->isValidPdf($body)) {
            throw new RuntimeException("Downloaded file is not a valid PDF: {$sourceUrl}");
        }

        $checksum = hash('sha256', $body);
        $relativePath = $targetRelativePath ?: $this->buildRelativePath($sourceUrl, $checksum);
        $absolutePath = storage_path('app/'.$relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create directory {$directory}");
        }

        if (file_put_contents($absolutePath, $body) === false) {
            throw new RuntimeException("Could not write PDF to {$absolutePath}");
        }

        clearstatcache(true, $absolutePath);
        $writtenSize = filesize($absolutePath);

        if ($writtenSize === false || $writtenSize !== $size) {
            @unlink($absolutePath);
            throw new RuntimeException('Written PDF size mismatch after download.');
        }

        return [
            'path' => $relativePath,
            'size' => $size,
            'checksum' => $checksum,
        ];
    }

    private function buildRelativePath(string $sourceUrl, string $checksum): string
    {
        $directory = trim((string) config('adala.download.storage_directory', 'adala/pdfs'), '/');
        $filename = substr($checksum, 0, 16).'.pdf';

        return $directory.'/'.$filename;
    }

    private function isValidPdf(string $contents): bool
    {
        return str_starts_with($contents, '%PDF');
    }
}
