<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

class Document extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'title',
        'language',
        'type',
        'source_file',
        'source_url',
        'official_url',
        'group',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('doc_lang_counts');
            Cache::forget('doc_group_counts');
            Cache::forget('total_documents');
        });
        static::deleted(function () {
            Cache::forget('doc_lang_counts');
            Cache::forget('doc_group_counts');
            Cache::forget('total_documents');
        });
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'doc_title' => $this->title,
            'doc_language' => $this->language,
            'doc_type' => $this->type,
            'doc_source_file' => $this->source_file,
            'group' => $this->group,
        ];
    }
}
