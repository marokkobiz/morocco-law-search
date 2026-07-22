<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Document extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'title',
        'language',
        'type',
        'source_file',
        'group',
    ];

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
