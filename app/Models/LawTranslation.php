<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawTranslation extends Model
{
    protected $fillable = [
        'law_id',
        'source_language',
        'target_language',
        'translated_title',
        'translated_content',
        'provider',
    ];

    public function law(): BelongsTo
    {
        return $this->belongsTo(Law::class);
    }
}
