<?php

namespace App\Http\Controllers\Api;

use App\Models\Article;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class LawController
{
    public function overview()
    {
        $totalArticles = Article::count();
        $totalDocuments = Document::count();

        $categories = Document::select('group')
            ->whereNotNull('group')
            ->groupBy('group')
            ->selectRaw('`group` as category, COUNT(DISTINCT articles.id) as articleCount')
            ->join('articles', 'articles.document_id', '=', 'documents.id')
            ->get();

        return response()->json([
            'totalArticles' => $totalArticles,
            'totalSources' => $totalDocuments,
            'totalCategories' => $categories->count(),
            'categories' => $categories,
        ]);
    }

    public function suggestions(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $articles = Article::query()
            ->join('documents', 'articles.document_id', '=', 'documents.id')
            ->select(
                'articles.id',
                'articles.article_number',
                'documents.title as document_title',
                'documents.type as doc_type'
            )
            ->where(function ($q) use ($query) {
                $q->where('articles.text', 'LIKE', "%{$query}%")
                    ->orWhere('articles.article_number', 'LIKE', "%{$query}%")
                    ->orWhere('documents.title', 'LIKE', "%{$query}%");
            })
            ->take(10)
            ->get();

        $suggestions = $articles->map(fn ($a) => [
            'label' => "{$a->document_title} — {$a->article_number}",
            'text' => $a->article_number,
            'type' => $a->doc_type,
        ]);

        return response()->json(['suggestions' => $suggestions]);
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $language = $request->get('language');
        $type = $request->get('type');
        $perPage = min((int) $request->get('per_page', 20), 100);

        if (empty($query)) {
            return response()->json(['query' => '', 'count' => 0, 'results' => []]);
        }

        $articles = Article::query()
            ->join('documents', 'articles.document_id', '=', 'documents.id')
            ->select(
                'articles.id',
                'articles.document_id',
                'articles.text',
                'articles.article_number',
                'articles.path',
                'articles.chapter',
                'documents.title as document_title',
                'documents.type as doc_type',
                'documents.language as doc_language',
                'documents.group as category',
                'documents.source_file as doc_source_file'
            )
            ->where(function ($q) use ($query) {
                $q->where('articles.text', 'LIKE', "%{$query}%")
                    ->orWhere('articles.article_number', 'LIKE', "%{$query}%")
                    ->orWhere('articles.chapter', 'LIKE', "%{$query}%")
                    ->orWhere('documents.title', 'LIKE', "%{$query}%");
            });

        if ($language) {
            $articles->where('documents.language', $language);
        }

        if ($type) {
            $articles->where('documents.type', $type);
        }

        $total = $articles->count();
        $results = $articles
            ->orderBy('articles.sort_key')
            ->paginate($perPage);

        $results->getCollection()->transform(fn ($article) => [
            'id' => $article->id,
            'document_id' => $article->document_id,
            'title' => $article->document_title,
            'content' => $article->text,
            'article_number' => $article->article_number,
            'category' => $article->category,
            'source_name' => $article->doc_type,
            'source_file' => $article->doc_source_file,
            'chapter' => $article->chapter,
            'path' => $article->path,
        ]);

        return response()->json([
            'query' => $query,
            'count' => $total,
            'results' => $results->items(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'per_page' => $results->perPage(),
        ]);
    }

    public function translate(string $law)
    {
        return response()->json([
            'message' => 'Translation not yet implemented',
            'law_id' => $law,
        ], 501);
    }

    public function sync()
    {
        $exitCode = Artisan::call('laws:sync');

        return response()->json([
            'success' => $exitCode === 0,
            'output' => Artisan::output(),
        ]);
    }
}
