<?php

namespace App\Services\Adala;

use App\Models\AdalaDocument;
use App\Models\Law;
use App\Models\LegalDocument;
use App\Services\CorpusEmbeddingService;
use App\Services\LawPdfImportService;
use App\Services\LegacyLawCorpusImportService;
use RuntimeException;

class AdalaDocumentPipelineService
{
    public function __construct(
        private readonly AdalaPdfDownloadService $downloader,
        private readonly AdalaUrlNormalizer $urls,
        private readonly LawPdfImportService $pdfImporter,
        private readonly LegacyLawCorpusImportService $corpusImporter,
        private readonly CorpusEmbeddingService $embeddingService,
    ) {
    }

    public function download(AdalaDocument $document): AdalaDocument
    {
        if ($document->hasReachedStatus(AdalaDocument::STATUS_DOWNLOADED)
            && $document->local_path
            && $this->downloader->validateExistingFile($document->local_path, $document->file_checksum)) {
            return $document->fresh();
        }

        $document->markStatus(AdalaDocument::STATUS_DOWNLOADING);

        $result = $this->downloader->download(
            $document->normalized_url,
            $document->local_path,
        );

        $document->markStatus(AdalaDocument::STATUS_DOWNLOADED, [
            'local_path' => $result['path'],
            'file_size_bytes' => $result['size'],
            'file_checksum' => $result['checksum'],
            'error_message' => null,
        ]);

        return $document->fresh();
    }

    public function import(AdalaDocument $document): AdalaDocument
    {
        if ($document->hasReachedStatus(AdalaDocument::STATUS_IMPORTED) && $this->hasLegacyImport($document)) {
            return $document->fresh();
        }

        if (!$document->local_path || !$this->downloader->validateExistingFile($document->local_path, $document->file_checksum)) {
            $document = $this->download($document);
        }

        $absolutePath = storage_path('app/'.$document->local_path);
        $pdfContent = file_get_contents($absolutePath);

        if ($pdfContent === false) {
            throw new RuntimeException('Could not read downloaded PDF at '.$document->local_path);
        }

        $count = $this->pdfImporter->importSource([
            'sourceUrl' => $document->source_url,
            'documentTitle' => $document->title ?: $this->urls->titleFromUrl($document->source_url),
            'sourceName' => (string) config('adala.import.source_name'),
            'category' => $document->category ?: (string) config('adala.import.category', 'adala'),
            'language' => $document->language ?: (string) config('adala.import.default_language', 'fr'),
            'pdfContent' => $pdfContent,
            'timeoutMs' => (int) config('adala.import.timeout_ms', 600000),
            'allowUnstructuredFallback' => true,
        ]);

        $document->markStatus(AdalaDocument::STATUS_IMPORTED, [
            'laws_imported_count' => $count,
            'error_message' => null,
        ]);

        return $document->fresh();
    }

    public function buildCorpus(AdalaDocument $document): AdalaDocument
    {
        if ($document->hasReachedStatus(AdalaDocument::STATUS_CHUNKED) && $this->hasCorpusChunks($document)) {
            $legalDocument = LegalDocument::query()->where('source_url', $document->source_url)->first();
            $document->markStatus(AdalaDocument::STATUS_CHUNKED, [
                'legal_document_id' => $legalDocument?->id,
                'chunks_created' => $this->embeddingService->countChunksForSourceUrl($document->source_url),
            ]);

            return $document->fresh();
        }

        if (!$this->hasLegacyImport($document)) {
            $document = $this->import($document);
        }

        $summary = $this->corpusImporter->import(null, [$document->source_url]);
        $errors = $summary['errors'] ?? [];

        if ($errors !== []) {
            throw new RuntimeException($errors[0]['message'] ?? 'Corpus import failed.');
        }

        $legalDocument = LegalDocument::query()->where('source_url', $document->source_url)->first();
        $chunksCreated = $this->embeddingService->countChunksForSourceUrl($document->source_url);

        $document->markStatus(AdalaDocument::STATUS_CHUNKED, [
            'legal_document_id' => $legalDocument?->id,
            'chunks_created' => $chunksCreated,
            'error_message' => null,
        ]);

        return $document->fresh();
    }

    public function embed(AdalaDocument $document): AdalaDocument
    {
        $totalChunks = $this->embeddingService->countChunksForSourceUrl($document->source_url);

        if ($totalChunks === 0) {
            $document = $this->buildCorpus($document);
            $totalChunks = (int) $document->chunks_created;
        }

        $embeddedCount = $this->embeddingService->countEmbeddedChunksForSourceUrl($document->source_url);

        if ($document->hasReachedStatus(AdalaDocument::STATUS_EMBEDDED) && $embeddedCount >= $totalChunks && $totalChunks > 0) {
            return $document->fresh();
        }

        $summary = $this->embeddingService->embedChunksForSourceUrl($document->source_url);
        $embeddedCount = $this->embeddingService->countEmbeddedChunksForSourceUrl($document->source_url);

        if ($embeddedCount < $totalChunks) {
            throw new RuntimeException("Embedding incomplete: {$embeddedCount}/{$totalChunks} chunks embedded.");
        }

        $document->markStatus(AdalaDocument::STATUS_EMBEDDED, [
            'chunks_embedded' => $embeddedCount,
            'error_message' => null,
        ]);

        return $document->fresh();
    }

    public function syncVectors(AdalaDocument $document): AdalaDocument
    {
        $totalChunks = $this->embeddingService->countChunksForSourceUrl($document->source_url);
        $embeddedCount = $this->embeddingService->countEmbeddedChunksForSourceUrl($document->source_url);

        if ($embeddedCount < $totalChunks) {
            $document = $this->embed($document);
            $embeddedCount = (int) $document->chunks_embedded;
        }

        $summary = $this->embeddingService->syncAndVerifySourceUrl($document->source_url);

        $document->markStatus(AdalaDocument::STATUS_VECTORIZED, [
            'chunks_vectorized' => $summary['verified'],
            'error_message' => null,
        ]);

        return $document->fresh();
    }

    public function verifyAndComplete(AdalaDocument $document): AdalaDocument
    {
        $document = $this->syncVectors($document);

        $totalChunks = $this->embeddingService->countChunksForSourceUrl($document->source_url);
        $embeddedCount = $this->embeddingService->countEmbeddedChunksForSourceUrl($document->source_url);
        $vectorized = (int) $document->chunks_vectorized;

        if ($totalChunks === 0) {
            throw new RuntimeException('No corpus chunks found after processing.');
        }

        if ($embeddedCount < $totalChunks || $vectorized < $totalChunks) {
            throw new RuntimeException("Indexing verification failed: chunks={$totalChunks}, embedded={$embeddedCount}, vectorized={$vectorized}");
        }

        $document->markStatus(AdalaDocument::STATUS_COMPLETED);
        $document->run?->incrementStat('documents_completed');

        return $document->fresh();
    }

    public function processEntireDocument(AdalaDocument $document): AdalaDocument
    {
        if ($document->status === AdalaDocument::STATUS_COMPLETED) {
            return $document;
        }

        if (!$document->processing_started_at) {
            $document->forceFill(['processing_started_at' => now()])->save();
        }

        return $this->verifyAndComplete(
            $this->syncVectors(
                $this->embed(
                    $this->buildCorpus(
                        $this->import(
                            $this->download($document->fresh())
                        )
                    )
                )
            )
        );
    }

    private function hasLegacyImport(AdalaDocument $document): bool
    {
        return Law::query()->where('source_url', $document->source_url)->exists();
    }

    private function hasCorpusChunks(AdalaDocument $document): bool
    {
        return $this->embeddingService->countChunksForSourceUrl($document->source_url) > 0;
    }
}
