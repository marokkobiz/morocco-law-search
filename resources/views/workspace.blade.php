@extends('layouts.workspace')

@section('title', 'Search | MarocLoi')

@php
    $locale = app()->getLocale();
    $c = fn($en, $fr, $ar) => $locale === 'fr' ? $fr : ($locale === 'ar' ? $ar : $en);
@endphp

@section('workspace-content')
    <main class="flex-1">
        <div class="mx-auto bg-white h-full overflow-hidden flex flex-col">

            {{-- Results Header --}}
            <div id="results-header" class="hidden shrink-0 px-8 py-6 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900" id="results-title"></h2>
                        <p class="text-sm text-gray-500 mt-1">
                            <span id="result-count"></span>
                        </p>
                    </div>
                </div>
            </div>

            {{-- Translation Warning --}}
            <div id="translation-warning" class="hidden m-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800"></div>

            {{-- Initial State --}}
            <div id="results-initial" class="grow flex flex-col items-center justify-center px-8 py-8">

                <div class="w-24 h-24 rounded-3xl bg-linear-to-br from-blue-50 to-indigo-100 border border-blue-100 shadow-sm flex items-center justify-center">
                    <svg class="w-11 h-11 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <h1 class="mt-8 text-3xl font-bold text-gray-900 text-center">
                    {{ $c('Moroccan Legal Research', 'Recherche Juridique Marocaine', 'البحث القانوني المغربي') }}
                </h1>

                <p class="mt-4 max-w-2xl text-center text-gray-500 leading-relaxed">
                    {{ $c(
                        'Search through Moroccan laws, regulations, court decisions and legal articles using natural language.',
                        'Recherchez dans les lois, règlements, décisions judiciaires et articles juridiques marocains en langage naturel.',
                        'ابحث في القوانين والأنظمة والأحكام القضائية والمقالات القانونية المغربية باستخدام اللغة الطبيعية.',
                    ) }}
                </p>

                {{-- Quick Categories --}}
                <div class="flex flex-wrap items-center justify-center gap-3 mt-10">
                    @foreach ([['q' => 'immobilier', 'label' => $c('Immobilier', 'Immobilier', 'عقار')], ['q' => 'travail', 'label' => $c('Travail', 'Travail', 'شغل')], ['q' => 'commerce', 'label' => $c('Commerce', 'Commerce', 'تجارة')], ['q' => 'famille', 'label' => $c('Famille', 'Famille', 'أسرة')], ['q' => 'fiscalite', 'label' => $c('Fiscalité', 'Fiscalité', 'ضرائب')]] as $cat)
                        <button type="button" data-quick="{{ $cat['q'] }}"
                            class="quick-btn px-5 py-2.5 rounded-xl bg-white border border-gray-200 text-sm font-medium text-gray-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200 cursor-pointer shadow-sm">
                            {{ $cat['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- Example Card --}}
                <div class="mt-12 w-full max-w-3xl">
                    <div class="rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white shadow-lg">

                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m8-3A9 9 0 1112 3a9 9 0 019 9z"/>
                                </svg>
                            </div>

                            <div>
                                <h3 class="font-semibold text-lg">
                                    {{ $c('Try asking', 'Essayez de demander', 'جرّب أن تسأل') }}
                                </h3>

                                <p class="text-blue-100 mt-2">
                                    {{ $c(
                                        'What are the rights of an employee after dismissal?',
                                        'Quels sont les droits d\'un salarié après licenciement ?',
                                        'ما هي حقوق العامل بعد الفصل من العمل؟',
                                    ) }}
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            {{-- Loading --}}
            <div id="results-loading" class="hidden grow flex flex-col items-center justify-center">
                <div class="flex items-center justify-center gap-3">
                    <div class="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-sm text-gray-500">
                        {{ $c('Searching...', 'Recherche...', 'جاري البحث...') }}
                    </span>
                </div>
            </div>

            {{-- Empty --}}
            <div id="results-empty" class="hidden grow flex flex-col items-center justify-center">

                <div class="w-20 h-20 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto">
                    <svg class="w-10 h-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>

                <p class="mt-5 text-gray-500">
                    {{ $c('No results found', 'Aucun résultat trouvé', 'لم يتم العثور على نتائج') }}
                </p>

            </div>

            {{-- Results --}}
            <div id="results-list" class="space-y-4 p-8"></div>

        </div>
    </main>

    {{-- Sidebar --}}
    <aside class="hidden lg:flex flex-col w-80 shrink-0 border-l border-gray-200 bg-white overflow-hidden" style="height: calc(100vh - 3.5rem); position: sticky; top: 3.5rem;">

        <div class="p-5 border-b border-gray-100 shrink-0">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">{{ $c('Corpus Overview', 'Aperçu du corpus', 'نظرة عامة على المعطيات') }}</h3>
            <div class="grid grid-cols-3 gap-3">
                <div class="text-center p-3 rounded-xl bg-linear-to-b from-gray-50 to-white border border-gray-100">
                    <strong class="block text-xl font-bold text-gray-900" id="stat-articles">—</strong>
                    <span class="block mt-1 text-[10px] font-semibold text-gray-400 uppercase">{{ $c('Articles', 'Articles', 'مواد') }}</span>
                </div>
                <div class="text-center p-3 rounded-xl bg-linear-to-b from-gray-50 to-white border border-gray-100">
                    <strong class="block text-xl font-bold text-gray-900" id="stat-sources">—</strong>
                    <span class="block mt-1 text-[10px] font-semibold text-gray-400 uppercase">{{ $c('Sources', 'Sources', 'مصادر') }}</span>
                </div>
                <div class="text-center p-3 rounded-xl bg-linear-to-b from-gray-50 to-white border border-gray-100">
                    <strong class="block text-xl font-bold text-gray-900" id="stat-areas">—</strong>
                    <span class="block mt-1 text-[10px] font-semibold text-gray-400 uppercase">{{ $c('Areas', 'Domaines', 'مجالات') }}</span>
                </div>
            </div>
        </div>

        <div class="p-5 border-b border-gray-100 min-h-0 flex-1 overflow-y-auto">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ $c('Browse Categories', 'Parcourir catégories', 'تصفح التصنيفات') }}</h3>
            <div id="category-list" class="space-y-0.5"></div>
        </div>

        <div class="p-5 shrink-0">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ $c('Assistant', 'Assistant', 'المساعد') }}</h3>
            <button id="assistant-toggle"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-linear-to-r from-blue-600 to-indigo-600 text-white text-sm font-semibold hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 cursor-pointer shadow-sm">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                {{ $c('Open Assistant', 'Ouvrir l\'Assistant', 'فتح المساعد') }}
            </button>
        </div>

    </aside>

    {{-- Floating assistant popup --}}
    <div id="assistant-popup" class="hidden fixed bottom-6 right-6 z-50 w-96 bg-white rounded-2xl border border-gray-200 shadow-2xl flex flex-col" style="height: 32rem; max-height: calc(100vh - 8rem);">

        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-linear-to-r from-blue-600 to-indigo-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">{{ $c('Legal Assistant', 'Assistant juridique', 'المساعد القانوني') }}</h3>
                    <p class="text-[10px] text-blue-200">{{ $c('Ask anything about Moroccan law', 'Posez une question sur le droit marocain', 'اسأل عن القانون المغربي') }}</p>
                </div>
            </div>
            <button id="assistant-close" type="button" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors cursor-pointer">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3" id="chat-feed">
            <div class="flex items-start gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-linear-to-br from-blue-600 to-indigo-600 flex items-center justify-center shrink-0 mt-0.5 shadow-sm">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div class="bg-gray-50 rounded-xl rounded-tl-none px-3.5 py-2.5 border border-gray-100 flex-1">
                    <p class="text-xs text-gray-600 leading-relaxed">{{ $c('Hi, ask about Moroccan legal topics.', 'Bonjour, posez une question sur le droit marocain.', 'مرحباً، اسأل عن القانون المغربي.') }}</p>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-gray-100">
            <form id="chat-form" class="flex items-center gap-2">
                <input id="chat-input" type="text" placeholder="{{ $c('Ask a question...', 'Posez une question...', 'اسأل سؤالاً...') }}"
                    class="flex-1 h-10 px-3.5 rounded-xl border border-gray-200 bg-white text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50">
                <button type="submit"
                    class="h-10 w-10 flex items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-500 text-white transition-colors cursor-pointer shrink-0 shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    {{-- Floating action button --}}
    {{-- <button id="assistant-fab"
        class="fixed bottom-6 right-6 z-40 w-14 h-14 rounded-2xl bg-linear-to-r from-blue-600 to-indigo-600 text-white shadow-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 cursor-pointer flex items-center justify-center hover:scale-105">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
    </button> --}}
@endsection

@push('scripts')
    @vite('resources/js/search-workspace.js')
@endpush
