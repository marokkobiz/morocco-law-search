<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRun extends Model
{
    protected $fillable = [
        'legal_source_id',
        'import_type',
        'source_url',
        'source_file_path',
        'started_at',
        'finished_at',
        'status',
        'documents_imported',
        'articles_extracted',
        'chunks_created',
        'errors',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'errors' => 'array',
            'metadata' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LegalSource::class, 'legal_source_id');
    }
}
