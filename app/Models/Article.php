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

    public function getCleanTextAttribute(): string
    {
        $text = $this->text;

        // Strip page markers: --- PAGE X --- (French) or --- X EGAP --- (Arabic) or ---PAGE1--- (no spaces)
        $text = preg_replace('/---\s*(?:\d+\s*(?:PAGE|EGAP)|(?:PAGE|EGAP)\s*\d+)\s*---/i', '', $text);

        // Strip any remaining bare --- lines
        $text = preg_replace('/^---+$/m', '', $text);

        // Strip standalone page numbers like "-2-", "-14-", "-15-" etc. on their own line
        $text = preg_replace('/^\s*-\d+-\s*$/m', '', $text);

        // Strip trailing footnote reference digits at end of lines (e.g. "Tunisie1." -> "Tunisie.")
        $text = preg_replace('/(\S)\d{1,2}\s*$/m', '$1', $text);

        // Collapse multiple blank lines into at most two
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim leading/trailing whitespace
        $text = trim($text);

        return $text;
    }
}
