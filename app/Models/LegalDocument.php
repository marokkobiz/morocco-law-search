<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    protected $fillable = [
        'legal_source_id',
        'current_version_id',
        'document_title',
        'document_type',
        'law_reference',
        'bo_number',
        'publication_date',
        'effective_date',
        'language',
        'domain',
        'subdomain',
        'tags',
        'source_url',
        'checksum',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'effective_date' => 'date',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LegalSource::class, 'legal_source_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'current_version_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(LegalArticle::class);
    }
}
