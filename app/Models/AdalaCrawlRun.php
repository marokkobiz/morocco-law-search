<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdalaCrawlRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'status',
        'seed_urls',
        'documents_discovered',
        'documents_completed',
        'documents_failed',
        'pages_crawled',
        'started_at',
        'finished_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'seed_urls' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function pages(): HasMany
    {
        return $this->hasMany(AdalaCrawlPage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdalaDocument::class);
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => self::STATUS_RUNNING,
            'started_at' => $this->started_at ?? now(),
            'finished_at' => null,
        ])->save();
    }

    public function markCompleted(): void
    {
        $this->forceFill([
            'status' => self::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();
    }

    public function incrementStat(string $column): void
    {
        $this->increment($column);
    }
}
