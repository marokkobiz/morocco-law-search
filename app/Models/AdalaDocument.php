<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdalaDocument extends Model
{
    public const STATUS_DISCOVERED = 'discovered';

    public const STATUS_DOWNLOADING = 'downloading';

    public const STATUS_DOWNLOADED = 'downloaded';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_CHUNKED = 'chunked';

    public const STATUS_EMBEDDED = 'embedded';

    public const STATUS_VECTORIZED = 'vectorized';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const PIPELINE_ORDER = [
        self::STATUS_DISCOVERED,
        self::STATUS_DOWNLOADING,
        self::STATUS_DOWNLOADED,
        self::STATUS_IMPORTED,
        self::STATUS_CHUNKED,
        self::STATUS_EMBEDDED,
        self::STATUS_VECTORIZED,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'adala_crawl_run_id',
        'source_url',
        'normalized_url',
        'url_hash',
        'title',
        'language',
        'category',
        'document_type',
        'publication_date',
        'local_path',
        'file_checksum',
        'file_size_bytes',
        'status',
        'retry_count',
        'error_message',
        'last_attempt_at',
        'processing_started_at',
        'processing_finished_at',
        'processing_duration_ms',
        'laws_imported_count',
        'chunks_created',
        'chunks_embedded',
        'chunks_vectorized',
        'legal_document_id',
        'metadata',
        'discovered_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'metadata' => 'array',
            'discovered_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processing_finished_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AdalaCrawlRun::class, 'adala_crawl_run_id');
    }

    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    public function hasReachedStatus(string $status): bool
    {
        $current = array_search($this->status, self::PIPELINE_ORDER, true);
        $target = array_search($status, self::PIPELINE_ORDER, true);

        if ($current === false || $target === false) {
            return $this->status === self::STATUS_COMPLETED;
        }

        return $current >= $target;
    }

    public function markStatus(string $status, ?array $extra = null): void
    {
        $payload = array_merge([
            'status' => $status,
            'last_attempt_at' => now(),
        ], $extra ?? []);

        if ($status === self::STATUS_DOWNLOADING && !$this->processing_started_at) {
            $payload['processing_started_at'] = now();
        }

        if ($status === self::STATUS_COMPLETED) {
            $payload['completed_at'] = now();
            $payload['processing_finished_at'] = now();

            if ($this->processing_started_at) {
                $payload['processing_duration_ms'] = (int) $this->processing_started_at->diffInMilliseconds(now());
            }
        }

        $this->forceFill($payload)->save();
    }

    public function markFailed(string $message, ?string $failedStep = null): void
    {
        $wasFailed = $this->status === self::STATUS_FAILED;
        $metadata = $this->metadata ?? [];

        if ($failedStep) {
            $metadata['failed_step'] = $failedStep;
        }

        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'metadata' => $metadata,
            'last_attempt_at' => now(),
            'retry_count' => $this->retry_count + 1,
            'processing_finished_at' => now(),
            'processing_duration_ms' => $this->processing_started_at
                ? (int) $this->processing_started_at->diffInMilliseconds(now())
                : null,
        ])->save();

        if (!$wasFailed) {
            $this->run?->incrementStat('documents_failed');
        }
    }
}
