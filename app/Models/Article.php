<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'document_id',
        'article_number',
        'text',
        'sort_key',
        'path',
        'slug',
        'chapter',
        'depth',
        'page',
        'footnotes',
    ];

    protected function casts(): array
    {
        return [
            'footnotes' => 'array',
        ];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'doc_title' => $this->document?->title,
            'doc_language' => $this->document?->language,
            'doc_type' => $this->document?->type,
            'doc_source_file' => $this->document?->source_file,
            'article_num' => $this->article_number,
            'sort_key' => $this->sort_key,
            'text' => $this->text,
            'path' => $this->path,
            'slug' => $this->slug,
            'breadcrumb_chapter' => $this->chapter,
            'depth' => $this->depth,
            'group' => $this->document?->group,
        ];
    }
}
