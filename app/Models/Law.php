<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Law extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'title',
        'article_number',
        'content',
        'tags',
        'document_title',
        'law_reference',
        'category',
        'source_name',
        'source_url',
        'language',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LawTranslation::class);
    }
}
