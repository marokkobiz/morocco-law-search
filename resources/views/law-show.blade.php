@extends('layouts.workspace')

@section('title', $document->title . ' | MarocLoi')

@php
    $locale = app()->getLocale();
    $c = fn($en, $fr, $ar) => $locale === 'fr' ? $fr : ($locale === 'ar' ? $ar : $en);
    $langLabel = match($document->language) { 'fr' => 'Français', 'ar' => 'العربية', 'en' => 'English', default => strtoupper($document->language) };
    $isRtl = $document->language === 'ar';
@endphp

@section('workspace-content')
    <main class="flex-1 relative overflow-hidden flex flex-col">

        {{-- Sticky top bar --}}
        <div class="shrink-0 border-b border-gray-200 bg-white/80 backdrop-blur-md z-10">
            <div class="flex items-center gap-3 px-4 sm:px-6 h-12">
                <a href="{{ route('app.workspace') }}"
                    class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-gray-900 hover:bg-gray-100 px-2.5 py-1.5 rounded-lg transition-colors no-underline">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $isRtl ? 'M9 5l7 7-7 7' : 'M15 19l-7-7 7-7' }}"/>
                    </svg>
                    {{ $c('Back', 'Retour', 'رجوع') }}
                </a>
                <div class="h-4 w-px bg-gray-200"></div>
                <h2 class="text-xs font-semibold text-gray-500 truncate flex-1 {{ $isRtl ? 'text-right' : '' }}" dir="auto">{{ $document->title }}</h2>
                <button id="sidebar-toggle" type="button"
                    class="lg:hidden shrink-0 w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center transition-colors cursor-pointer">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Scrollable content --}}
        <div class="flex-1 overflow-y-auto" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

            {{-- Document header --}}
            <div class="px-4 sm:px-8 lg:px-12 pt-8 pb-6 border-b border-gray-100">
                <div class="max-w-3xl mx-auto" dir="auto">
                    <div class="flex items-center gap-2 mb-3 {{ $isRtl ? 'flex-row-reverse' : '' }}">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider {{ $document->language === 'ar' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }}">
                            {{ $langLabel }}
                        </span>
                        @if($document->type)
                            <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">{{ $document->type }}</span>
                        @endif
                        @if($document->group)
                            <span class="text-[11px] font-semibold text-indigo-500">{{ $document->group }}</span>
                        @endif
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 leading-tight tracking-tight">
                        {{ $document->title }}
                    </h1>

                    <div class="flex items-center gap-4 mt-3 text-sm text-gray-400 {{ $isRtl ? 'flex-row-reverse' : '' }}">
                        <span>{{ $articles->count() }} {{ $c('articles', 'articles', 'مادة') }}</span>
                        @if($document->source_file)
                            <span class="truncate max-w-[250px]" title="{{ $document->source_file }}">
                                {{ basename($document->source_file, '.' . pathinfo($document->source_file, PATHINFO_EXTENSION)) }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Articles --}}
            <div class="px-4 sm:px-8 lg:px-12 py-8" dir="auto">
                <div class="max-w-3xl mx-auto">

                    @forelse($articles as $article)
                        @php
                            $cleanText = $article->clean_text;
                            $isEmpty = trim($cleanText) === '';
                        @endphp

                        @if($isEmpty)
                            @continue
                        @endif

                        <div id="article-{{ $article->id }}" class="scroll-mt-20 mb-8 pb-8 border-b border-gray-100 last:border-b-0 last:mb-0 last:pb-0">

                            {{-- Article number + chapter header --}}
                            @if($article->article_number || $article->chapter)
                                <div class="{{ $isRtl ? 'mb-4' : 'mb-3' }}">
                                    @if($article->article_number)
                                        <div class="flex items-center gap-2.5 {{ $isRtl ? 'flex-row-reverse' : '' }}">
                                            <span class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-lg bg-blue-50 text-xs font-bold text-blue-700 border border-blue-100">
                                                {{ $article->article_number }}
                                            </span>
                                            @if($article->chapter)
                                                <span class="text-sm font-semibold text-gray-700">{{ $article->chapter }}</span>
                                            @endif
                                        </div>
                                    @elseif($article->chapter)
                                        <h2 class="text-lg font-bold text-gray-900">{{ $article->chapter }}</h2>
                                    @endif

                                    @if($article->path && $article->path !== $article->chapter)
                                        <p class="text-[11px] text-gray-400 mt-1 {{ $isRtl ? 'text-right' : '' }}">{{ $article->path }}</p>
                                    @endif
                                </div>
                            @endif

                            {{-- Article body --}}
                            <div class="text-[15px] text-gray-700 leading-[1.85] {{ $isRtl ? 'text-right' : '' }}" dir="auto">
                                {!! nl2br(e($cleanText)) !!}
                            </div>

                            {{-- Footnotes --}}
                            @if($article->footnotes && count($article->footnotes))
                                <div class="mt-4 {{ $isRtl ? 'pr-3 border-r-2 border-gray-100' : 'pl-3 border-l-2 border-gray-100' }}">
                                    @foreach($article->footnotes as $i => $fn)
                                        <p class="text-xs text-gray-400 leading-relaxed mb-1 last:mb-0" dir="auto">
                                            <sup class="font-semibold text-gray-500">{{ $i + 1 }}</sup>
                                            {{ is_array($fn) ? ($fn['text'] ?? $fn['content'] ?? '') : $fn }}
                                        </p>
                                    @endforeach
                                </div>
                            @endif

                            @if($article->page)
                                <p class="mt-2 text-[10px] text-gray-300 {{ $isRtl ? 'text-right' : '' }}">{{ $c('p.', 'p.', 'ص') }} {{ $article->page }}</p>
                            @endif

                        </div>
                    @empty
                        <div class="text-center py-20">
                            <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto">
                                <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <p class="mt-4 text-sm text-gray-400">{{ $c('No articles yet.', 'Aucun article.', 'لا توجد مواد.') }}</p>
                        </div>
                    @endforelse

                </div>
            </div>

        </div>
    </main>

    {{-- Sidebar overlay (mobile) --}}
    <div id="sidebar-overlay" class="hidden fixed inset-0 z-30 bg-black/30 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside id="sidebar-panel"
        class="flex flex-col w-72 shrink-0 border-l border-gray-200 bg-white overflow-hidden fixed lg:sticky inset-y-0 ltr:right-0 rtl:left-0 z-50 lg:z-auto shadow-2xl lg:shadow-none hidden lg:flex"
        style="height: calc(100vh - 3.5rem); top: 3.5rem;">

        <div class="p-4 border-b border-gray-100">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 {{ $isRtl ? 'text-right' : '' }}">
                {{ $c('Document', 'Document', 'المستند') }}</h3>
            <div class="space-y-2">
                <div>
                    <p class="text-xs font-medium text-gray-900 leading-snug" dir="auto">{{ $document->title }}</p>
                </div>
                <div class="flex items-center gap-2 text-[11px] text-gray-400 {{ $isRtl ? 'flex-row-reverse' : '' }}">
                    <span>{{ strtoupper($document->language) }}</span>
                    @if($document->type)
                        <span class="text-gray-300">&middot;</span>
                        <span>{{ $document->type }}</span>
                    @endif
                    @if($document->group)
                        <span class="text-gray-300">&middot;</span>
                        <span>{{ $document->group }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-4 min-h-0 flex-1 overflow-y-auto">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2 {{ $isRtl ? 'text-right' : '' }}">
                {{ $c('Contents', 'Sommaire', 'المحتويات') }}</h3>
            <nav class="space-y-0.5">
                @forelse($articles as $article)
                    @php
                        $sidebarText = $article->article_number
                            ? ($article->chapter ?: Str::limit($article->clean_text, 50))
                            : ($article->chapter ?: Str::limit($article->clean_text, 50));
                    @endphp
                    @if(trim($sidebarText) === '') @continue @endif
                    <a href="#article-{{ $article->id }}"
                        class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[13px] transition-colors hover:bg-gray-50 no-underline group {{ $article->article_number ? 'text-gray-600' : 'text-gray-900 font-semibold' }} {{ $isRtl ? 'flex-row-reverse text-right' : '' }}">
                        @if($article->article_number)
                            <span class="shrink-0 w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold text-gray-400 group-hover:text-blue-600 group-hover:bg-blue-50">
                                {{ $article->article_number }}
                            </span>
                        @endif
                        <span class="truncate group-hover:text-blue-700" dir="auto">{{ $sidebarText }}</span>
                    </a>
                @empty
                    <p class="text-xs text-gray-400 py-2">{{ $c('No articles', 'Aucun article', 'لا توجد مواد') }}</p>
                @endforelse
            </nav>
        </div>
    </aside>
@endsection

@push('scripts')
    <script>
        const sidebar = document.getElementById('sidebar-panel');
        const overlay = document.getElementById('sidebar-overlay');
        document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
            sidebar?.classList.toggle('hidden');
            overlay?.classList.toggle('hidden');
        });
        overlay?.addEventListener('click', () => {
            sidebar?.classList.add('hidden');
            overlay?.classList.add('hidden');
        });

        document.querySelectorAll('#sidebar-panel a[href^="#"]').forEach(link => {
            link.addEventListener('click', (e) => {
                const id = link.getAttribute('href').slice(1);
                const target = document.getElementById(id);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    sidebar?.classList.add('hidden');
                    overlay?.classList.add('hidden');
                }
            });
        });

        const contentArea = document.querySelector('.flex-1.overflow-y-auto');
        const sidebarLinks = document.querySelectorAll('#sidebar-panel nav a');
        if (contentArea && sidebarLinks.length) {
            const articles = contentArea.querySelectorAll('[id^="article-"]');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        sidebarLinks.forEach(l => l.classList.remove('bg-gray-50', 'text-blue-700'));
                        const link = document.querySelector(`#sidebar-panel a[href="#${entry.target.id}"]`);
                        if (link) {
                            link.classList.add('bg-gray-50', 'text-blue-700');
                        }
                    }
                });
            }, { root: contentArea, threshold: 0.2 });
            articles.forEach(a => observer.observe(a));
        }
    </script>
@endpush
