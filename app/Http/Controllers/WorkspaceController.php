<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController
{
    public function index(): View
    {
        $documents = Document::withCount('articles')->orderBy('title')->get();
        $stats = [
            'total_articles' => Article::count(),
            'total_documents' => Document::count(),
        ];

        return view('workspace', compact('documents', 'stats'));
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
