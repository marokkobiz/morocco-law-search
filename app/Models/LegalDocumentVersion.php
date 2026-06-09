<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocumentVersion extends Model
{
    protected $fillable = [
        'legal_document_id',
        'version_number',
        'source_url',
        'source_file_path',
        'checksum',
        'status',
        'publication_date',
        'effective_date',
        'imported_at',
        'raw_text',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'effective_date' => 'date',
            'imported_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(LegalArticle::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(LegalChunk::class);
    }
}
