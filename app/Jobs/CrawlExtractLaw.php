<?php

namespace App\Jobs;

use App\Models\CrawlPage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class CrawlExtractLaw implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public CrawlPage $page
    ) {
        $this->onQueue('crawler');
    }

    public function handle(): void
    {
        $this->page->update(['status' => 'downloading']);

        try {
            $response = Http::timeout(config('crawler.http_timeout', 30))
                ->withOptions(['verify' => false])
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                ->get($this->page->url);

            $this->page->update(['http_status' => $response->status()]);

            if (!$response->successful()) {
                $this->page->update([
                    'status' => 'failed',
                    'error_message' => "HTTP {$response->status()}",
                ]);
                return;
            }

            $rawText = '';

            if ($this->page->content_type === 'pdf') {
                $tempPath = tempnam(sys_get_temp_dir(), 'crawler_pdf_');
                try {
                    file_put_contents($tempPath, $response->body());

                    if (class_exists(\Spatie\PdfToText\Pdf::class)) {
                        try {
                            $binPath = config('crawler.pdftotext_bin', 'pdftotext');
                            $rawText = \Spatie\PdfToText\Pdf::getText($tempPath, $binPath);
                        } catch (\Throwable $e) {
                            $rawText = '';
                        }
                    }

                    if (empty(trim($rawText)) && class_exists(\Smalot\PdfParser\Parser::class)) {
                        $fileSize = filesize($tempPath);
                        if ($fileSize < 50 * 1024 * 1024) {
                            $oldLimit = ini_get('memory_limit');
                            try {
                                ini_set('memory_limit', '1G');
                                $parser = new \Smalot\PdfParser\Parser();
                                $pdf = $parser->parseFile($tempPath);
                                $rawText = $pdf->getText();
                            } catch (\Throwable $e) {
                                $rawText = '';
                            } finally {
                                ini_set('memory_limit', $oldLimit);
                            }
                        }
                    }

                    // OCR fallback for scanned PDFs
                    if (empty(trim($rawText))) {
                        $rawText = $this->ocrPdf($tempPath);
                    }
                } finally {
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                }

                if (!empty(trim($rawText))) {
                    $this->page->session()->increment('pdfs_downloaded');
                }
            } else {
                $html = $response->body();
                $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
                $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
                $rawText = strip_tags($html);
                $rawText = preg_replace('/\s+/', ' ', $rawText);
                $rawText = trim($rawText);
            }

            if (empty(trim($rawText))) {
                $this->page->update([
                    'status' => 'failed',
                    'error_message' => 'No text content extracted',
                ]);
                return;
            }

            $this->page->update([
                'raw_text' => $rawText,
                'status' => 'processing_ai',
            ]);

            CrawlCleanWithAI::dispatch($this->page);
        } catch (\Throwable $e) {
            $this->page->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function ocrPdf(string $pdfPath): string
    {
        $tesseractBin = $this->normalizePath(config('crawler.tesseract_bin', ''));
        $pdftoppmBin = $this->normalizePath(config('crawler.pdftoppm_bin', ''));
        $tessdataDir = $this->normalizePath(config('crawler.tesseract_data_dir', ''));

        $tesseractExists = $tesseractBin && is_file($tesseractBin);
        $pdftoppmExists = $pdftoppmBin && is_file($pdftoppmBin);

        if (!$tesseractExists) {
            return '';
        }

        // Add pdftoppm/bin dir to PATH for DLL resolution
        $binDir = dirname($pdftoppmBin);
        if ($pdftoppmExists && is_dir($binDir)) {
            $oldPath = getenv('PATH');
            putenv('PATH=' . $binDir . ';' . $oldPath);
        }

        // Convert PDF to images via pdftoppm
        if ($pdftoppmExists) {
            $imageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid();
            @mkdir($imageDir, 0777, true);

            $cmd = '"' . $pdftoppmBin . '" -png -r 300 "' . $pdfPath . '" "' . $imageDir . DIRECTORY_SEPARATOR . 'page" 2>&1';
            exec($cmd, $output, $code);

            if ($code === 0) {
                $fullText = '';
                $images = glob($imageDir . DIRECTORY_SEPARATOR . '*.png');
                sort($images);

                $tessdataArg = $tessdataDir ? ' --tessdata-dir "' . $tessdataDir . '"' : '';

                foreach ($images as $image) {
                    $outPref = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_result_' . uniqid();
                    $ocrCmd = '"' . $tesseractBin . '" "' . $image . '" "' . $outPref . '" -l ara' . $tessdataArg . ' 2>&1';
                    exec($ocrCmd, $o, $c);

                    $resultFile = $outPref . '.txt';
                    if ($c === 0 && file_exists($resultFile)) {
                        $fullText .= file_get_contents($resultFile) . "\n";
                        @unlink($resultFile);
                    }
                }

                foreach ($images as $f) @unlink($f);
                @rmdir($imageDir);

                return trim($fullText);
            }

            foreach (glob($imageDir . DIRECTORY_SEPARATOR . '*') as $f) @unlink($f);
            @rmdir($imageDir);
        }

        // Fallback: try Tesseract directly on the PDF
        $outPref = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_result_' . uniqid();
        $tessdataArg = $tessdataDir ? ' --tessdata-dir "' . $tessdataDir . '"' : '';
        $cmd = '"' . $tesseractBin . '" "' . $pdfPath . '" "' . $outPref . '" -l ara' . $tessdataArg . ' 2>&1';
        exec($cmd, $output, $code);

        $resultFile = $outPref . '.txt';
        $text = '';
        if ($code === 0 && file_exists($resultFile)) {
            $text = file_get_contents($resultFile);
            @unlink($resultFile);
        }

        return trim($text);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') return $path;
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $real = realpath($path);
        return $real !== false ? $real : $path;
    }
}
