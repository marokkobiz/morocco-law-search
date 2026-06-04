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
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
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
