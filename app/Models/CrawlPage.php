<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlPage extends Model
{
    protected $fillable = [
        'session_id',
        'url',
        'url_hash',
        'depth',
        'content_type',
        'http_status',
        'raw_text',
        'ai_json',
        'domain',
        'status',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_json' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CrawlSession::class, 'session_id');
    }
}
