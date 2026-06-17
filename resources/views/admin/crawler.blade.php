@extends('layouts.app')

@section('title', 'AI Crawler Dashboard | MarocLoi')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">AI Web Crawler</h1>
        <p class="mt-1 text-sm text-gray-500">Paste a root URL to crawl Moroccan legal sources. The system discovers pages, extracts text, and uses AI to clean and classify content.</p>
    </div>

    {{-- Start Form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <form id="crawl-form" method="POST" action="{{ route('crawler.start') }}" class="flex flex-col sm:flex-row gap-4">
            @csrf
            <div class="flex-1">
                <label for="root_url" class="sr-only">Root URL</label>
                <input type="url" name="root_url" id="root_url" required
                    placeholder="https://www.sgg.gov.ma/arabe/..."
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors">
            </div>
            <button type="submit" id="start-btn"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors">
                Start Crawl
            </button>
        </form>
        <p class="mt-2 text-xs text-gray-400">
            {{-- Queue worker note: php artisan queue:work --queue=crawler --}}
            Crawl jobs run on the <code class="text-gray-600 bg-gray-100 px-1 rounded">crawler</code> queue. Run multiple workers for faster processing.
        </p>
    </div>

    {{-- Top Stats Cards --}}
    <div id="stats-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pages Discovered</p>
            <p id="stat-pages" class="mt-1 text-3xl font-bold text-gray-900">{{ $latestSession?->pages_discovered ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">PDFs Downloaded</p>
            <p id="stat-pdfs" class="mt-1 text-3xl font-bold text-gray-900">{{ $latestSession?->pdfs_downloaded ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Laws Stored (AI)</p>
            <p id="stat-laws" class="mt-1 text-3xl font-bold text-blue-600">{{ $latestSession?->laws_stored ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Session Status</p>
            <p id="stat-status" class="mt-1 text-3xl font-bold text-gray-900 flex items-center gap-2">
                <span id="status-text">{{ $latestSession ? ucfirst($latestSession->status) : 'Idle' }}</span>
                <span id="status-spinner" class="{{ $latestSession && in_array($latestSession->status, ['pending', 'running']) ? '' : 'hidden' }} inline-block w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></span>
            </p>
        </div>
    </div>

    {{-- Pipeline Funnel --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Pipeline Funnel</h2>
        <div id="funnel-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="text-center p-3 rounded-lg bg-yellow-50 border border-yellow-200">
                <p class="text-xs font-semibold text-yellow-700 uppercase">Discovered</p>
                <p id="funnel-discovered" class="text-2xl font-bold text-yellow-800">{{ $latestSession ? ($latestSession->pages()->where('status', 'discovered')->count()) : 0 }}</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-blue-50 border border-blue-200">
                <p class="text-xs font-semibold text-blue-700 uppercase">Downloading</p>
                <p id="funnel-downloading" class="text-2xl font-bold text-blue-800">{{ $latestSession ? ($latestSession->pages()->where('status', 'downloading')->count()) : 0 }}</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-purple-50 border border-purple-200">
                <p class="text-xs font-semibold text-purple-700 uppercase">AI Processing</p>
                <p id="funnel-processing_ai" class="text-2xl font-bold text-purple-800">{{ $latestSession ? ($latestSession->pages()->where('status', 'processing_ai')->count()) : 0 }}</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-green-50 border border-green-200">
                <p class="text-xs font-semibold text-green-700 uppercase">Completed</p>
                <p id="funnel-completed" class="text-2xl font-bold text-green-800">{{ $latestSession ? ($latestSession->pages()->where('status', 'completed')->count()) : 0 }}</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-red-50 border border-red-200">
                <p class="text-xs font-semibold text-red-700 uppercase">Failed</p>
                <p id="funnel-failed" class="text-2xl font-bold text-red-800">{{ $latestSession ? ($latestSession->pages()->where('status', 'failed')->count()) : 0 }}</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-gray-50 border border-gray-200">
                <p class="text-xs font-semibold text-gray-700 uppercase">Queue</p>
                <p id="stat-queue" class="text-2xl font-bold text-gray-800">—</p>
            </div>
        </div>
    </div>

    {{-- AI Status + Recent Errors --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">AI Status</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Articles Stored</span>
                    <span id="stat-articles" class="text-lg font-bold text-blue-600">{{ \App\Models\LegalArticle::count() }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">AI Completed Pages</span>
                    <span id="stat-ai-done" class="text-lg font-bold text-green-600">0</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">AI Currently Processing</span>
                    <span id="stat-ai-busy" class="text-lg font-bold text-purple-600">0</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Jobs in Queue</span>
                    <span id="stat-queue-size" class="text-lg font-bold text-gray-600">—</span>
                </div>
            </div>
        </div>
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Recent Errors</h2>
                @if (isset($recentErrors) && $recentErrors->count() > 0)
                    <form method="POST" action="{{ route('crawler.retry-all', $latestSession) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-500 border border-red-300 rounded px-2 py-1">Retry All Failed</button>
                    </form>
                @endif
            </div>
            <div id="errors-container">
                @if (isset($recentErrors) && $recentErrors->count() > 0)
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach ($recentErrors as $e)
                            <div class="p-3 rounded-lg bg-red-50 border border-red-100 flex items-center justify-between">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-mono text-gray-700 truncate" title="{{ $e->url }}">{{ \Illuminate\Support\Str::limit($e->url, 50) }}</p>
                                    <p class="text-xs text-red-600 mt-1">{{ $e->error_message }}</p>
                                </div>
                                <form method="POST" action="{{ route('crawler.retry', $e) }}" class="ml-3 shrink-0">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-500">Retry</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400">No errors yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Pages Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Discovered Pages</h2>
            <span class="text-xs text-gray-400">Latest 50</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">URL</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="pages-tbody" class="divide-y divide-gray-100">
                    @if ($latestSession && $latestSession->pages()->count() > 0)
                        @foreach ($latestSession->pages()->latest('id')->limit(50)->get() as $i => $page)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-6 py-4 max-w-xs truncate text-gray-900 font-mono text-xs" title="{{ $page->url }}">{{ \Illuminate\Support\Str::limit($page->url, 60) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($page->content_type === 'pdf')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 uppercase">PDF</span>
                                    @elseif ($page->content_type === 'html')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 uppercase">HTML</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'discovered' => 'bg-yellow-100 text-yellow-800',
                                            'downloading' => 'bg-blue-100 text-blue-800',
                                            'processing_ai' => 'bg-purple-100 text-purple-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'failed' => 'bg-red-100 text-red-800',
                                        ];
                                        $color = $statusColors[$page->status] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $color }}">{{ str_replace('_', ' ', $page->status) }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $page->domain ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($page->status === 'failed')
                                        <form method="POST" action="{{ route('crawler.retry', $page) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-500">Retry</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">No crawl sessions yet. Start one above.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    const sessionId = new URLSearchParams(window.location.search).get('session');
    if (!sessionId) return;

    var els = {};
    ['pages-tbody', 'stat-pages', 'stat-pdfs', 'stat-laws', 'status-text', 'status-spinner', 'start-btn',
     'funnel-discovered', 'funnel-downloading', 'funnel-processing_ai', 'funnel-completed', 'funnel-failed', 'stat-queue',
     'stat-articles', 'stat-ai-done', 'stat-ai-busy', 'stat-queue-size', 'errors-container'].forEach(function(id) {
        els[id] = document.getElementById(id);
    });

    var badgeColors = {
        'discovered': 'bg-yellow-100 text-yellow-800',
        'downloading': 'bg-blue-100 text-blue-800',
        'processing_ai': 'bg-purple-100 text-purple-800',
        'completed': 'bg-green-100 text-green-800',
        'failed': 'bg-red-100 text-red-800',
    };

    function badge(status) {
        var c = badgeColors[status] || 'bg-gray-100 text-gray-800';
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' + c + '">' + status.replace(/_/g, ' ') + '</span>';
    }

    function typeBadge(type) {
        if (!type) return '<span class="text-gray-400">—</span>';
        var c = type === 'pdf' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800';
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' + c + ' uppercase">' + type + '</span>';
    }

    function truncate(u, m) { return u ? (u.length > m ? u.substring(0, m) + '…' : u) : '—'; }

    function renderPages(pages) {
        if (!pages || pages.length === 0) {
            els['pages-tbody'].innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">No pages.</td></tr>';
            return;
        }
        var h = '';
        for (var i = 0; i < pages.length; i++) {
            var p = pages[i];
            var r = p.status === 'failed'
                ? '<form method="POST" action="/crawler/retry/' + p.id + '" class="inline"><input type="hidden" name="_token" value="' + (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '') + '"><button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-500">Retry</button></form>'
                : '<span class="text-xs text-gray-400">—</span>';
            h += '<tr class="hover:bg-gray-50">' +
                '<td class="px-6 py-4 whitespace-nowrap text-gray-500">' + (i + 1) + '</td>' +
                '<td class="px-6 py-4 max-w-xs truncate text-gray-900 font-mono text-xs" title="' + (p.url || '') + '">' + truncate(p.url, 60) + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap">' + typeBadge(p.content_type) + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap">' + badge(p.status) + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">' + (p.domain || '—') + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap">' + r + '</td></tr>';
        }
        els['pages-tbody'].innerHTML = h;
    }

    function renderErrors(errors) {
        if (!errors || errors.length === 0) {
            els['errors-container'].innerHTML = '<p class="text-sm text-gray-400">No errors yet.</p>';
            return;
        }
        var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var h = '<div class="flex items-center justify-between mb-3">' +
            '<span class="text-sm font-semibold text-gray-900">' + errors.length + ' failed pages</span>' +
            '<form method="POST" action="/crawler/retry-all/' + sessionId + '" class="inline">' +
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-500 border border-red-300 rounded px-2 py-1">Retry All Failed</button></form>' +
            '</div>' +
            '<div class="space-y-2 max-h-64 overflow-y-auto">';
        for (var i = 0; i < errors.length; i++) {
            var e = errors[i];
            h += '<div class="p-3 rounded-lg bg-red-50 border border-red-100 flex items-center justify-between">' +
                '<div class="min-w-0 flex-1">' +
                '<p class="text-xs font-mono text-gray-700 truncate" title="' + (e.url || '') + '">' + truncate(e.url, 50) + '</p>' +
                '<p class="text-xs text-red-600 mt-1">' + (e.error_message || 'Unknown') + '</p>' +
                '</div>' +
                '<form method="POST" action="/crawler/retry/' + e.id + '" class="ml-3 shrink-0">' +
                '<input type="hidden" name="_token" value="' + csrf + '">' +
                '<button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-500">Retry</button></form>' +
                '</div>';
        }
        h += '</div>';
        els['errors-container'].innerHTML = h;
    }

    function updateStats(stats) {
        if (!stats) return;
        els['stat-pages'].textContent = stats.total_discovered ?? 0;
        els['stat-pdfs'].textContent = stats.total_pdfs ?? 0;
        els['stat-laws'].textContent = stats.total_laws_stored ?? 0;
        els['stat-articles'].textContent = stats.articles_stored ?? 0;
        els['stat-ai-done'].textContent = stats.ai_pages_completed ?? 0;
        els['stat-ai-busy'].textContent = stats.ai_pages_processing ?? 0;
        els['stat-queue-size'].textContent = stats.queue_size ?? '—';
        els['stat-queue'].textContent = stats.queue_size ?? '—';

        // Funnel
        var b = stats.status_breakdown || {};
        els['funnel-discovered'].textContent = b.discovered ?? 0;
        els['funnel-downloading'].textContent = b.downloading ?? 0;
        els['funnel-processing_ai'].textContent = b.processing_ai ?? 0;
        els['funnel-completed'].textContent = b.completed ?? 0;
        els['funnel-failed'].textContent = b.failed ?? 0;
    }

    function updateSession(session) {
        if (!session) return;
        var s = session.status || 'idle';
        els['status-text'].textContent = s.charAt(0).toUpperCase() + s.slice(1);
        if (s === 'pending' || s === 'running') {
            els['status-spinner'].classList.remove('hidden');
        } else {
            els['status-spinner'].classList.add('hidden');
        }
    }

    function fetchStatus() {
        fetch('/crawler/status/' + sessionId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                renderPages(d.pages);
                updateStats(d.stats);
                updateSession(d.session);
                renderErrors(d.recent_errors);
            })
            .catch(function(err) { console.error(err); });
    }

    fetchStatus();
    setInterval(fetchStatus, 5000);
})();
</script>
@endpush
