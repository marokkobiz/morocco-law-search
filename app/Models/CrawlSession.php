<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrawlSession extends Model
{
    protected $fillable = [
        'root_url',
        'user_id',
        'status',
        'pages_discovered',
        'pdfs_downloaded',
        'laws_stored',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CrawlPage::class, 'session_id');
    }
}
