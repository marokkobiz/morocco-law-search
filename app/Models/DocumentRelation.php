<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRelation extends Model
{
    protected $fillable = [
        'from_legal_document_id',
        'to_legal_document_id',
        'legal_document_version_id',
        'relation_type',
        'relation_date',
        'details',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'relation_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function fromDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'from_legal_document_id');
    }

    public function toDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'to_legal_document_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }
}
