@extends('layouts.workspace')

@section('title', $document->title . ' | MarocLoi')

@php
    $locale = app()->getLocale();
    $c = fn($en, $fr, $ar) => $locale === 'fr' ? $fr : ($locale === 'ar' ? $ar : $en);
    $langLabel = match ($document->language) {
        'fr' => 'Français',
        'ar' => 'العربية',
        'en' => 'English',
        default => strtoupper($document->language),
    };
    $isRtl = $document->language === 'ar';

    $officialUrl = $document->official_url;
    $sourceUrl = $document->source_url;
    $crawledAt = $document->created_at
        ? \Illuminate\Support\Carbon::parse($document->created_at)->format('d M Y')
        : null;
@endphp

@section('workspace-content')
    <main class="relative flex flex-1 flex-col overflow-hidden">

        {{-- Reading progress bar --}}
        <div id="reading-progress" class="fixed left-0 top-0 z-50 h-0.5 bg-blue-600 transition-[width] duration-150 ease-out"
            style="width:0%"></div>

        {{-- Sticky top bar --}}
        <div class="z-10 shrink-0 border-b border-gray-200 bg-white/80 backdrop-blur-md">
            <div class="flex h-12 items-center gap-3 px-4 sm:px-6">
                <a href="{{ route('app.workspace') }}"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-gray-500 no-underline transition-colors hover:bg-gray-100 hover:text-gray-900">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="{{ $isRtl ? 'M9 5l7 7-7 7' : 'M15 19l-7-7 7-7' }}" />
                    </svg>
                    {{ $c('Back', 'Retour', 'رجوع') }}
                </a>
                <div class="h-4 w-px bg-gray-200"></div>
                <h2 class="{{ $isRtl ? 'text-right' : '' }} flex-1 truncate text-xs font-semibold text-gray-500"
                    dir="auto">{{ $document->title }}</h2>

                @if ($officialUrl)
                    <a href="{{ $officialUrl }}" target="_blank" rel="noopener"
                        class="hidden shrink-0 items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white no-underline transition-colors hover:bg-blue-700 sm:inline-flex">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6v6m-9 3l9-9" />
                        </svg>
                        {{ $c('Official document', 'Document officiel', 'الوثيقة الرسمية') }}
                    </a>
                @endif

                <button id="sidebar-toggle" type="button"
                    class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg transition-colors hover:bg-gray-100 lg:hidden">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Scrollable content --}}
        <div id="doc-content" class="flex-1 overflow-y-auto" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

            {{-- Document header --}}
            <div class="border-b border-gray-100 px-4 pb-6 pt-8 sm:px-8 lg:px-12">
                <div class="mx-auto max-w-4xl" dir="auto">
                    <div class="{{ $isRtl ? 'flex-row-reverse' : '' }} mb-3 flex flex-wrap items-center gap-2">
                        <span
                            class="{{ $document->language === 'ar' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }} inline-flex items-center rounded px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider">
                            {{ $langLabel }}
                        </span>
                        @if ($document->type)
                            <span
                                class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $document->type }}</span>
                        @endif
                        @if ($document->group)
                            <span
                                class="inline-flex items-center rounded bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-600">{{ $document->group }}</span>
                        @endif
                        @if ($crawledAt)
                            <span class="inline-flex items-center gap-1 text-[11px] text-gray-400"
                                title="{{ $c('Added on', 'Ajouté le', 'أُضيف في') }}">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ $crawledAt }}
                            </span>
                        @endif
                    </div>

                    <h1 class="text-2xl font-bold text-justify leading-tight tracking-tight text-gray-900 sm:text-3xl">
                        {{ $document->title }}
                    </h1>

                    <div
                        class="{{ $isRtl ? 'flex-row-reverse' : '' }} mt-4 flex flex-wrap items-center gap-4 text-sm text-gray-400">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            {{ $articles->count() }} {{ $c('articles', 'articles', 'مادة') }}
                        </span>

                        @if ($document->source_file)
                            <span class="inline-flex max-w-[240px] items-center gap-1.5 truncate"
                                title="{{ $document->source_file }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span
                                    class="truncate">{{ basename($document->source_file, '.' . pathinfo($document->source_file, PATHINFO_EXTENSION)) }}</span>
                            </span>
                        @endif
                    </div>

                    {{-- Action buttons --}}
                    <div class="{{ $isRtl ? 'flex-row-reverse' : '' }} mt-5 flex flex-wrap items-center gap-2">
                        @if ($officialUrl)
                            <a href="{{ $officialUrl }}" target="_blank" rel="noopener"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white no-underline transition-colors hover:bg-blue-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6v6m-9 3l9-9" />
                                </svg>
                                {{ $c('Open official document', 'Ouvrir le document officiel', 'فتح الوثيقة الرسمية') }}
                            </a>
                        @endif
                        @if ($sourceUrl)
                            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener"
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-600 no-underline transition-colors hover:bg-gray-100 hover:text-gray-900">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13.828 10.172a4 4 0 010 5.656l-4 4a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l4-4a4 4 0 015.656 5.656l-1.5 1.5" />
                                </svg>
                                {{ $c('Source page', 'Page source', 'الصفحة المصدر') }}
                            </a>
                        @endif
                        <button type="button" onclick="copyPageLink()"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.828 10.172a4 4 0 010 5.656l-4 4a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l4-4a4 4 0 015.656 5.656l-1.5 1.5" />
                            </svg>
                            {{ $c('Copy link', 'Copier le lien', 'نسخ الرابط') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Articles --}}
            <div class="px-4 py-8 sm:px-8 lg:px-8" dir="auto">
                <div class="mx-auto max-w-4xl">

                    @forelse($articles as $article)
                        @php
                            $cleanText = $article->clean_text;
                            $isEmpty = trim($cleanText) === '';
                            $isPreamble =
                                $article->article_number === 'Preamble' || $article->article_number === 'Full Text';
                        @endphp

                        @if ($isEmpty)
                            @continue
                        @endif

                        <div id="article-{{ $article->id }}"
                            class="group/article mb-8 scroll-mt-20 border-b border-gray-100 pb-8 last:mb-0 last:border-b-0 last:pb-0">

                            {{-- Article number + chapter header --}}
                            @if ($article->article_number || $article->chapter)
                                <div class="{{ $isRtl ? 'mb-4' : 'mb-3' }}">
                                    @if ($article->article_number)
                                        <div class="{{ $isRtl ? 'flex-row-reverse' : '' }} flex items-center gap-2.5">
                                            <span
                                                class="{{ $isPreamble ? 'bg-gray-50 text-gray-500 border border-gray-100' : 'bg-blue-50 text-blue-700 border border-blue-100' }} inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg px-2 text-xs font-bold">
                                                {{ $article->article_number }}
                                            </span>
                                            @if ($article->chapter)
                                                <span
                                                    class="text-sm font-semibold text-gray-700">{{ $article->chapter }}</span>
                                            @endif
                                        </div>
                                    @elseif($article->chapter)
                                        <h2 class="text-lg font-bold text-gray-900">{{ $article->chapter }}</h2>
                                    @endif

                                    @if ($article->path && $article->path !== $article->chapter)
                                        <p class="{{ $isRtl ? 'text-right' : '' }} mt-1 text-[11px] text-gray-400">
                                            {{ $article->path }}</p>
                                    @endif
                                </div>
                            @endif

                            @php
                                // Remove single newlines (PDF line breaks), keep double as paragraphs
                                $displayText = preg_replace('/(?<!\n)\n(?! *\n)/', ' ', $cleanText);
                                // Convert remaining double+ newlines to <br>
                            @endphp

                            {{-- Article body --}}
                            <div class="{{ $isRtl ? 'text-right' : '' }} text-[15px] text-justify leading-[1.85] text-gray-700"
                                dir="auto">
                                {{-- {!! nl2br(e($cleanText)) !!} --}}
                                {!! nl2br(e($displayText)) !!}
                            </div>

                            {{-- Footnotes --}}
                            @if ($article->footnotes && count($article->footnotes))
                                <div
                                    class="{{ $isRtl ? 'pr-3 border-r-2 border-gray-100' : 'pl-3 border-l-2 border-gray-100' }} mt-4">
                                    @foreach ($article->footnotes as $i => $fn)
                                        <p class="mb-1 text-xs leading-relaxed text-gray-400 last:mb-0" dir="auto">
                                            <sup class="font-semibold text-gray-500">{{ $i + 1 }}</sup>
                                            {{ is_array($fn) ? $fn['text'] ?? ($fn['content'] ?? '') : $fn }}
                                        </p>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Article footer: page + copy link --}}
                            <div class="{{ $isRtl ? 'flex-row-reverse' : '' }} mt-3 flex items-center gap-3">
                                @if ($article->page)
                                    <p class="text-[10px] text-gray-300">{{ $c('p.', 'p.', 'ص') }} {{ $article->page }}
                                    </p>
                                @endif
                                <button type="button" onclick="copyArticleLink('article-{{ $article->id }}')"
                                    class="inline-flex cursor-pointer items-center gap-1 text-[10px] text-gray-300 opacity-0 transition-opacity hover:text-blue-600 group-hover/article:opacity-100">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M13.828 10.172a4 4 0 010 5.656l-4 4a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l4-4a4 4 0 015.656 5.656l-1.5 1.5" />
                                    </svg>
                                    {{ $c('Copy article link', 'Copier le lien', 'نسخ رابط المادة') }}
                                </button>
                            </div>

                        </div>
                    @empty
                        <div class="py-20 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100">
                                <svg class="h-7 w-7 text-gray-300" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <p class="mt-4 text-sm text-gray-400">
                                {{ $c('No articles yet.', 'Aucun article.', 'لا توجد مواد.') }}</p>
                        </div>
                    @endforelse

                </div>
            </div>

        </div>
    </main>

    {{-- Sidebar overlay (mobile) --}}
    <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-black/30 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside id="sidebar-panel"
        class="fixed inset-y-0 z-50 flex hidden w-72 shrink-0 flex-col overflow-hidden border-l border-gray-200 bg-white shadow-2xl lg:sticky lg:z-auto lg:flex lg:shadow-none ltr:right-0 rtl:left-0"
        style="height: calc(100vh - 3.5rem); top: 3.5rem;">

        {{-- Document meta card --}}
        <div class="border-b border-gray-100 p-4">
            <h3
                class="{{ $isRtl ? 'text-right' : '' }} mb-3 text-[10px] font-bold uppercase tracking-widest text-gray-400">
                {{ $c('Document', 'Document', 'المستند') }}</h3>
            <div class="space-y-2">
                <p class="text-xs font-medium leading-snug text-gray-900" dir="auto">{{ $document->title }}</p>

                <div
                    class="{{ $isRtl ? 'flex-row-reverse' : '' }} flex flex-wrap items-center gap-2 text-[11px] text-gray-400">
                    <span>{{ strtoupper($document->language) }}</span>
                    @if ($document->type)
                        <span class="text-gray-300">&middot;</span>
                        <span>{{ $document->type }}</span>
                    @endif
                    @if ($document->group)
                        <span class="text-gray-300">&middot;</span>
                        <span class="text-indigo-500">{{ $document->group }}</span>
                    @endif
                    @if ($crawledAt)
                        <span class="text-gray-300">&middot;</span>
                        <span>{{ $crawledAt }}</span>
                    @endif
                </div>

                @if ($officialUrl)
                    <a href="{{ $officialUrl }}" target="_blank" rel="noopener"
                        class="mt-2 inline-flex w-full items-center gap-1.5 text-[11px] font-semibold text-blue-600 no-underline hover:text-blue-800">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6v6m-9 3l9-9" />
                        </svg>
                        {{ $c('Official document', 'Document officiel', 'الوثيقة الرسمية') }}
                    </a>
                @endif
                @if ($sourceUrl)
                    <a href="{{ $sourceUrl }}" target="_blank" rel="noopener"
                        class="inline-flex w-full items-center gap-1.5 text-[11px] font-semibold text-gray-500 no-underline hover:text-gray-800">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13.828 10.172a4 4 0 010 5.656l-4 4a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l4-4a4 4 0 015.656 5.656l-1.5 1.5" />
                        </svg>
                        {{ $c('Source page', 'Page source', 'الصفحة المصدر') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Table of contents --}}
        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <h3
                class="{{ $isRtl ? 'text-right' : '' }} mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-400">
                {{ $c('Contents', 'Sommaire', 'المحتويات') }}</h3>
            <nav class="space-y-0.5">
                @forelse($articles as $article)
                    @php
                        $sidebarText = $article->article_number
                            ? ($article->chapter ?:
                            Str::limit($article->clean_text, 50))
                            : ($article->chapter ?:
                            Str::limit($article->clean_text, 50));
                    @endphp
                    @if (trim($sidebarText) === '')
                        @continue
                    @endif
                    <a href="#article-{{ $article->id }}"
                        class="{{ $article->article_number ? 'text-gray-600' : 'text-gray-900 font-semibold' }} {{ $isRtl ? 'flex-row-reverse text-right' : '' }} group flex items-center gap-2 rounded-lg px-2 py-1.5 text-[13px] no-underline transition-colors hover:bg-gray-50">
                        @if ($article->article_number != 'Preamble')
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-[10px] font-bold text-gray-400 group-hover:bg-blue-50 group-hover:text-blue-600">
                                {{ $article->article_number }}
                            </span>
                        @endif

                        <span class="truncate group-hover:text-blue-700" dir="auto">{{ $sidebarText }}</span>
                    </a>
                @empty
                    <p class="py-2 text-xs text-gray-400">{{ $c('No articles', 'Aucun article', 'لا توجد مواد') }}</p>
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
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
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
            }, {
                root: contentArea,
                threshold: 0.2
            });
            articles.forEach(a => observer.observe(a));
        }

        // Reading progress bar
        const progressBar = document.getElementById('reading-progress');
        const scrollEl = contentArea || document;
        scrollEl.addEventListener('scroll', () => {
            const scrollable = scrollEl.scrollHeight - scrollEl.clientHeight;
            const pct = scrollable > 0 ? (scrollEl.scrollTop / scrollable) * 100 : 0;
            if (progressBar) progressBar.style.width = pct + '%';
        }, {
            passive: true
        });

        // Copy helpers
        function copyPageLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                flashToast('{{ $c('Link copied', 'Lien copié', 'تم نسخ الرابط') }}');
            });
        }

        function copyArticleLink(id) {
            const url = window.location.href.split('#')[0] + '#' + id;
            navigator.clipboard.writeText(url).then(() => {
                flashToast('{{ $c('Article link copied', 'Lien copié', 'تم نسخ رابط المادة') }}');
            });
        }

        function flashToast(message) {
            let el = document.getElementById('toast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'toast';
                el.className =
                    'fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-4 py-2 rounded-lg bg-gray-900 text-white text-xs font-medium shadow-lg transition-opacity duration-200 opacity-0 pointer-events-none';
                document.body.appendChild(el);
            }
            el.textContent = message;
            el.style.opacity = '1';
            clearTimeout(el._t);
            el._t = setTimeout(() => {
                el.style.opacity = '0';
            }, 1800);
        }
    </script>
@endpush
