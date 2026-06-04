<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalSource extends Model
{
    protected $fillable = [
        'name',
        'source_type',
        'source_url',
        'official_domain',
        'language',
        'checksum',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class);
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(ImportRun::class);
    }
}
