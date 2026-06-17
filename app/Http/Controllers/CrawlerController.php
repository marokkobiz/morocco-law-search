<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlDiscoverPages;
use App\Jobs\CrawlExtractLaw;
use App\Models\CrawlPage;
use App\Models\CrawlSession;
use Illuminate\Http\Request;

class CrawlerController extends Controller
{
    public function index(Request $request)
    {
        $sessionId = $request->query('session');

        if (!$sessionId) {
            $latestSession = CrawlSession::latest()->first();
            if ($latestSession) {
                return redirect()->route('crawler.dashboard', ['session' => $latestSession->id]);
            }
            return view('admin.crawler', [
                'latestSession' => null,
                'recentErrors' => collect(),
            ]);
        }

        $session = CrawlSession::findOrFail($sessionId);

        $recentErrors = $session->pages()
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'url', 'domain', 'error_message']);

        return view('admin.crawler', [
            'latestSession' => $session,
            'recentErrors' => $recentErrors,
        ]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'root_url' => 'required|url|max:1024',
        ]);

        $session = CrawlSession::create([
            'root_url' => $validated['root_url'],
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);

        CrawlDiscoverPages::dispatch($session, $session->root_url, 0);

        if ($request->ajax()) {
            return response()->json(['session_id' => $session->id]);
        }

        return redirect()->route('crawler.dashboard', ['session' => $session->id]);
    }

    public function status(CrawlSession $session)
    {
        $pages = $session->pages()
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get(['id', 'url', 'content_type', 'status', 'domain', 'error_message']);

        $statusBreakdown = $session->pages()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $recentErrors = $session->pages()
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'url', 'domain', 'error_message']);

        $stats = [
            'total_discovered' => $session->pages_discovered,
            'total_pdfs' => $session->pdfs_downloaded,
            'total_laws_stored' => $session->laws_stored,
            'status_breakdown' => $statusBreakdown,
            'queue_size' => \Illuminate\Support\Facades\DB::table('jobs')->count(),
            'articles_stored' => \App\Models\LegalArticle::count(),
            'ai_pages_completed' => $statusBreakdown['completed'] ?? 0,
            'ai_pages_processing' => $statusBreakdown['processing_ai'] ?? 0,
        ];

        return response()->json([
            'session' => $session,
            'pages' => $pages,
            'stats' => $stats,
            'recent_errors' => $recentErrors,
        ]);
    }

    public function retry(CrawlPage $page)
    {
        if ($page->status !== 'failed') {
            return redirect()->back();
        }

        $page->update([
            'status' => 'discovered',
            'error_message' => null,
            'ai_json' => null,
            'raw_text' => null,
            'processed_at' => null,
        ]);

        CrawlExtractLaw::dispatch($page);

        return redirect()->back();
    }

    public function retryAll(CrawlSession $session)
    {
        $failedPages = $session->pages()
            ->where('status', 'failed')
            ->get();

        foreach ($failedPages as $page) {
            $page->update([
                'status' => 'discovered',
                'error_message' => null,
                'ai_json' => null,
                'raw_text' => null,
                'processed_at' => null,
            ]);

            CrawlExtractLaw::dispatch($page);
        }

        return redirect()->back();
    }
}
