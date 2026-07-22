<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class LawController extends Controller
{
    public function overview()
    {
        $documents = Document::withCount('articles')->orderBy('title')->get();

        return response()->json($documents);
    }

    public function suggestions(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $articles = Article::search($query)
            ->take(10)
            ->get();

        return response()->json($articles);
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $language = $request->get('language');
        $type = $request->get('type');
        $perPage = min((int) $request->get('per_page', 20), 100);

        if (empty($query)) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $search = Article::search($query);

        if ($language) {
            $search->where('doc_language', $language);
        }

        if ($type) {
            $search->where('doc_type', $type);
        }

        $results = $search->paginate($perPage);

        return response()->json($results);
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
