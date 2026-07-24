<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkspaceController
{
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

        $langCounts = Document::select('language', DB::raw('COUNT(*) as cnt'))
            ->groupBy('language')
            ->pluck('cnt', 'language');

        $groupCounts = Document::whereNotNull('group')
            ->select('group', DB::raw('COUNT(*) as cnt'))
            ->groupBy('group')
            ->pluck('cnt', 'group');

        $stats = [
            'total_articles' => Article::count(),
            'total_documents' => Document::count(),
        ];

        return view('workspace', compact('documents', 'stats', 'langCounts', 'groupCounts', 'language', 'group'));
    }

    public function show(Document $document): View
    {
        $articles = $document->articles()
            ->orderBy('sort_key')
            ->get();

        $stats = [
            'total_articles' => $document->articles()->count(),
            'total_documents' => Document::count(),
        ];

        return view('law-show', compact('document', 'articles', 'stats'));
    }
}
