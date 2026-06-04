<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalArticle extends Model
{
    protected $fillable = [
        'legal_document_id',
        'legal_document_version_id',
        'legacy_law_id',
        'article_number',
        'article_title',
        'content',
        'language',
        'domain',
        'subdomain',
        'tags',
        'checksum',
        'sort_order',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }

    public function legacyLaw(): BelongsTo
    {
        return $this->belongsTo(Law::class, 'legacy_law_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(LegalChunk::class);
    }
}
