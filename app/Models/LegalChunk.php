<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalChunk extends Model
{
    protected $fillable = [
        'legal_article_id',
        'legal_document_version_id',
        'chunk_index',
        'content',
        'token_count',
        'domain',
        'subdomain',
        'tags',
        'checksum',
        'metadata',
        'embedding',
        'embedding_model',
        'embedding_checksum',
        'embedded_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
            'embedding' => 'array',
            'embedded_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(LegalArticle::class, 'legal_article_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }
}
