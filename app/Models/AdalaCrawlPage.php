<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdalaCrawlPage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CRAWLED = 'crawled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'adala_crawl_run_id',
        'page_url',
        'url_hash',
        'depth',
        'status',
        'pdfs_found',
        'error_message',
        'crawled_at',
    ];

    protected function casts(): array
    {
        return [
            'crawled_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AdalaCrawlRun::class, 'adala_crawl_run_id');
    }
}
