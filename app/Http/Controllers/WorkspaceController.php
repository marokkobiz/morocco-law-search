<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkspaceController
{
    private const CACHE_TTL = 300;

    public function index(Request $request): View
    {
        $language = $request->filled('language') ? $request->string('language')->value() : null;
        $group = $request->filled('group') ? $request->string('group')->value() : null;

        $query = Document::withCount('articles')->orderBy('title');

        if ($language) {
            $query->where('language', $language);
        }
        if ($group) {
            $query->where('group', $group);
        }

        $documents = $query->paginate(12)->withQueryString();

        $langCounts = Cache::remember('doc_lang_counts', self::CACHE_TTL, fn () =>
            Document::select('language', DB::raw('COUNT(*) as cnt'))
                ->groupBy('language')
                ->pluck('cnt', 'language')
        );

        $groupCounts = Cache::remember('doc_group_counts', self::CACHE_TTL, fn () =>
            Document::whereNotNull('group')
                ->select('group', DB::raw('COUNT(*) as cnt'))
                ->groupBy('group')
                ->pluck('cnt', 'group')
        );

        $stats = [
            'total_articles' => Cache::remember('total_articles', self::CACHE_TTL, fn () => Article::count()),
            'total_documents' => Cache::remember('total_documents', self::CACHE_TTL, fn () => Document::count()),
        ];

        return view('workspace', compact('documents', 'stats', 'langCounts', 'groupCounts', 'language', 'group'));
    }

    public function show(Document $document): View
    {
        $articles = $document->articles()
            ->orderBy('sort_key')
            ->get();

        $stats = [
            'total_articles' => Cache::remember('total_articles', self::CACHE_TTL, fn () => Article::count()),
            'total_documents' => Cache::remember('total_documents', self::CACHE_TTL, fn () => Document::count()),
        ];

        return view('law-show', compact('document', 'articles', 'stats'));
    }
}
