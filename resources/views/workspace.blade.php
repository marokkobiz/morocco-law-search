@extends('layouts.workspace')

@section('title', 'Search | MarocLoi')

@php
    $locale = app()->getLocale();
    $c = fn($en, $fr, $ar) => $locale === 'fr' ? $fr : ($locale === 'ar' ? $ar : $en);
@endphp

@section('workspace-content')
    <main class="flex-1 relative">
        <button id="sidebar-toggle" type="button"
            class="lg:hidden absolute top-3 ltr:right-3 rtl:left-3 z-10 w-8 h-8 rounded-lg bg-gray-800 hover:bg-gray-700 flex items-center justify-center transition-colors cursor-pointer shadow-md">
            <svg class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <div class="mx-auto bg-white h-full overflow-hidden flex flex-col">

            {{-- Results Header --}}
            <div id="results-header"
                class="hidden shrink-0 px-4 sm:px-8 py-4 sm:py-6 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <div class="flex items-center justify-between gap-3">
                    <div class="block sm:hidden">-</div>
                    <div class="min-w-0 flex-1 text-center">
                        <h2 class="text-base sm:text-lg lg:text-xl font-bold text-gray-900 truncate" id="results-title">
                        </h2>
                        <p class="text-xs sm:text-sm text-gray-500 mt-0.5 sm:mt-1">
                            <span id="result-count"></span>
                        </p>
                    </div>
                    <button id="clear-header-search" type="button"
                        class="shrink-0 text-[11px] sm:text-xs font-semibold text-gray-500 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 px-2.5 sm:px-3 py-1.5 rounded-lg transition-colors no-underline cursor-pointer whitespace-nowrap">
                        {{ $c('Clear', 'Effacer', 'مسح') }}
                    </button>
                </div>
            </div>

            {{-- Translation Warning --}}
            <div id="translation-warning"
                class="hidden m-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800"></div>

            {{-- Initial State --}}
            <div id="results-initial" class="grow flex flex-col items-center justify-center px-8 py-8">

                <div class="w-24 h-24 rounded-3xl border border-indigo-400 shadow-sm flex items-center justify-center">
                    <svg class="w-11 h-11 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <p class="mt-5 text-gray-500">
                    {{ $c('No results found', 'Aucun résultat trouvé', 'لم يتم العثور على نتائج') }}
                </p>

            </div>

            {{-- Results --}}
            <div id="results-list" class="hidden grow overflow-y-auto space-y-4 p-8"></div>

        </div>
    </main>

    {{-- Sidebar overlay (mobile) --}}
    <div id="sidebar-overlay" class="hidden fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside id="sidebar-panel"
        class="flex flex-col w-80 shrink-0 border-l border-gray-200 bg-white overflow-hidden fixed lg:sticky inset-y-0 ltr:right-0 rtl:left-0 z-50 lg:z-auto shadow-2xl lg:shadow-none hidden lg:flex"
        style="height: calc(100vh - 3.5rem); top: 3.5rem;">

        <div class="p-5 border border-gray-200 shrink-0">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">
                {{ $c('Corpus Overview', 'Aperçu du corpus', 'نظرة عامة على المعطيات') }}</h3>
            <div class="grid grid-cols-3 gap-3">
                <div class="text-center p-3 rounded-xl bg-linear-to-b from-gray-50 to-white border border-gray-100">
                    <strong class="block text-xl font-bold text-gray-900" id="stat-articles">—</strong>
                    <span
                        class="block mt-1 text-[10px] font-semibold text-gray-400 uppercase">{{ $c('Articles', 'Articles', 'مواد') }}</span>
                </div>
                <div class="text-center p-3 rounded-xl bg-linear-to-b from-gray-50 to-white border border-gray-100">
                    <strong class="block text-xl font-bold text-gray-900" id="stat-sources">—</strong>
                    <span
                        class="block mt-1 text-[10px] font-semibold text-gray-400 uppercase">{{ $c('Sources', 'Sources', 'مصادر') }}</span>
                </div>
                <div class="text-center p-3 rounded-xl bg-linear-to-b from-gray-50 to-white border border-gray-100">
                    <strong class="block text-xl font-bold text-gray-900" id="stat-areas">—</strong>
                    <span
                        class="block mt-1 text-[10px] font-semibold text-gray-400 uppercase">{{ $c('Areas', 'Domaines', 'مجالات') }}</span>
                </div>
            </div>
        </div>

        <div class="p-5 border border-gray-200 min-h-0 flex-1 overflow-y-auto">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">
                {{ $c('Browse Categories', 'Parcourir catégories', 'تصفح التصنيفات') }}</h3>
            <div id="category-loading" class="flex items-center justify-center gap-2 py-4">
                <div class="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                <span class="text-xs text-gray-400">{{ $c('Loading...', 'Chargement...', 'جار التحميل...') }}</span>
            </div>
            <div id="category-list" class="space-y-0.5"></div>
        </div>

    </aside>

    {{-- Floating assistant popup --}}
    <div id="assistant-popup"
        class="hidden fixed inset-4 sm:inset-auto sm:bottom-6 sm:ltr:left-6 sm:rtl:right-6 z-50 sm:w-96 bg-white rounded-2xl border border-gray-200 shadow-2xl flex flex-col sm:max-h-80 max-h-[calc(100vh-2rem)]">

        <div
            class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-linear-to-r from-blue-600 to-indigo-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                        fill="#FFFFFF" height="100px" width="100px" version="1.1" id="XMLID_74_" viewBox="0 0 24 24"
                        enable-background="new 0 0 24 24" xml:space="preserve">
                        <g id="assistant">
                            <g>
                                <path
                                    d="M9,12.5H8c-0.6,0-1-0.4-1-1v-1c0-0.6,0.4-1,1-1h1c0.6,0,1,0.4,1,1v1C10,12.1,9.6,12.5,9,12.5z" />
                            </g>
                            <g>
                                <path
                                    d="M16,12.5h-1c-0.6,0-1-0.4-1-1v-1c0-0.6,0.4-1,1-1h1c0.6,0,1,0.4,1,1v1C17,12.1,16.6,12.5,16,12.5z" />
                            </g>
                            <path d="M12,0c1.1,0,2,0.9,2,2s-0.9,2-2,2s-2-0.9-2-2S10.9,0,12,0z" />
                            <g>
                                <path
                                    d="M12,24c-2.7,0-4.9-1.6-5-4.2c-1.1-0.3-2.3-0.8-3.5-1.4L3,18.1v-4.6c0-4.6,3.5-8.4,8-8.9V1.5h2v3.1c4.5,0.5,8,4.3,8,8.9    v4.6l-0.5,0.3c-0.9,0.5-2.1,1-3.5,1.4C16.9,22.4,14.7,24,12,24z M9.1,20.2C9.5,21.5,10.6,22,12,22s2.6-0.5,2.9-1.8    C13.3,20.5,11.6,20.6,9.1,20.2z M5,16.9c2.7,1.3,5.3,1.6,7,1.6c3,0,5.4-0.8,7-1.6v-1.5c-1.8,0.9-4.5,1.1-7,1.1s-5.2-0.2-7-1.1    V16.9z M5.1,12.8c0.1,0.7,2.2,1.7,7,1.7c4.3,0,6.9-0.9,7-1.7c-0.3-3.5-3.3-6.3-7-6.3S5.4,9.3,5.1,12.8z" />
                            </g>
                        </g>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">
                        {{ $c('Legal Assistant', 'Assistant juridique', 'المساعد القانوني') }}</h3>
                    <p class="text-[10px] text-blue-200">
                        {{ $c('Ask anything about Moroccan law', 'Posez une question sur le droit marocain', 'اسأل عن القانون المغربي') }}
                    </p>
                </div>
            </div>
            <button id="assistant-close" type="button"
                class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors cursor-pointer">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3" id="chat-feed">
            <div class="flex items-start gap-2.5">
                <div
                    class="w-7 h-7 rounded-lg bg-linear-to-br from-blue-600 to-indigo-600 flex items-center justify-center shrink-0 mt-0.5 shadow-sm">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                        fill="#FFFFFF" height="100px" width="100px" version="1.1" id="XMLID_74_" viewBox="0 0 24 24"
                        enable-background="new 0 0 24 24" xml:space="preserve">
                        <g id="assistant">
                            <g>
                                <path
                                    d="M9,12.5H8c-0.6,0-1-0.4-1-1v-1c0-0.6,0.4-1,1-1h1c0.6,0,1,0.4,1,1v1C10,12.1,9.6,12.5,9,12.5z" />
                            </g>
                            <g>
                                <path
                                    d="M16,12.5h-1c-0.6,0-1-0.4-1-1v-1c0-0.6,0.4-1,1-1h1c0.6,0,1,0.4,1,1v1C17,12.1,16.6,12.5,16,12.5z" />
                            </g>
                            <path d="M12,0c1.1,0,2,0.9,2,2s-0.9,2-2,2s-2-0.9-2-2S10.9,0,12,0z" />
                            <g>
                                <path
                                    d="M12,24c-2.7,0-4.9-1.6-5-4.2c-1.1-0.3-2.3-0.8-3.5-1.4L3,18.1v-4.6c0-4.6,3.5-8.4,8-8.9V1.5h2v3.1c4.5,0.5,8,4.3,8,8.9    v4.6l-0.5,0.3c-0.9,0.5-2.1,1-3.5,1.4C16.9,22.4,14.7,24,12,24z M9.1,20.2C9.5,21.5,10.6,22,12,22s2.6-0.5,2.9-1.8    C13.3,20.5,11.6,20.6,9.1,20.2z M5,16.9c2.7,1.3,5.3,1.6,7,1.6c3,0,5.4-0.8,7-1.6v-1.5c-1.8,0.9-4.5,1.1-7,1.1s-5.2-0.2-7-1.1    V16.9z M5.1,12.8c0.1,0.7,2.2,1.7,7,1.7c4.3,0,6.9-0.9,7-1.7c-0.3-3.5-3.3-6.3-7-6.3S5.4,9.3,5.1,12.8z" />
                            </g>
                        </g>
                    </svg>
                </div>
                <div class="bg-gray-50 rounded-xl rounded-tl-none px-3.5 py-2.5 border border-gray-100 flex-1">
                    <p class="text-xs text-gray-600 leading-relaxed">
                        {{ $c('Hi, ask about Moroccan legal topics.', 'Bonjour, posez une question sur le droit marocain.', 'مرحباً، اسأل عن القانون المغربي.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-gray-100">
            <form id="chat-form" class="flex items-center gap-2">
                <input id="chat-input" type="text"
                    placeholder="{{ $c('Ask a question...', 'Posez une question...', 'اسأل سؤالاً...') }}"
                    class="flex-1 h-10 px-3.5 rounded-xl border border-gray-200 bg-white text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50">
                <button type="submit"
                    class="h-10 w-10 flex items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-500 text-white transition-colors cursor-pointer shrink-0 shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

    {{-- Floating action button --}}
    <button id="assistant-fab" type="button"
        class="group fixed bottom-6 ltr:left-6 rtl:right-6 z-40 flex items-center rounded-2xl bg-linear-to-r from-blue-600 to-indigo-600 text-white shadow-xl hover:from-blue-700 hover:to-indigo-700 cursor-pointer overflow-hidden w-14 hover:w-40 transition-all duration-300 ease-in-out">
        <span class="flex items-center justify-center w-14 h-14 shrink-0">
            <svg class="size-8" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                fill="#FFFFFF" height="100px" width="100px" version="1.1" id="XMLID_74_" viewBox="0 0 24 24"
                enable-background="new 0 0 24 24" xml:space="preserve">
                <g id="assistant">
                    <g>
                        <path
                            d="M9,12.5H8c-0.6,0-1-0.4-1-1v-1c0-0.6,0.4-1,1-1h1c0.6,0,1,0.4,1,1v1C10,12.1,9.6,12.5,9,12.5z" />
                    </g>
                    <g>
                        <path
                            d="M16,12.5h-1c-0.6,0-1-0.4-1-1v-1c0-0.6,0.4-1,1-1h1c0.6,0,1,0.4,1,1v1C17,12.1,16.6,12.5,16,12.5z" />
                    </g>
                    <path d="M12,0c1.1,0,2,0.9,2,2s-0.9,2-2,2s-2-0.9-2-2S10.9,0,12,0z" />
                    <g>
                        <path
                            d="M12,24c-2.7,0-4.9-1.6-5-4.2c-1.1-0.3-2.3-0.8-3.5-1.4L3,18.1v-4.6c0-4.6,3.5-8.4,8-8.9V1.5h2v3.1c4.5,0.5,8,4.3,8,8.9    v4.6l-0.5,0.3c-0.9,0.5-2.1,1-3.5,1.4C16.9,22.4,14.7,24,12,24z M9.1,20.2C9.5,21.5,10.6,22,12,22s2.6-0.5,2.9-1.8    C13.3,20.5,11.6,20.6,9.1,20.2z M5,16.9c2.7,1.3,5.3,1.6,7,1.6c3,0,5.4-0.8,7-1.6v-1.5c-1.8,0.9-4.5,1.1-7,1.1s-5.2-0.2-7-1.1    V16.9z M5.1,12.8c0.1,0.7,2.2,1.7,7,1.7c4.3,0,6.9-0.9,7-1.7c-0.3-3.5-3.3-6.3-7-6.3S5.4,9.3,5.1,12.8z" />
                    </g>
                </g>
            </svg>
        </span>
        <span
            class="text-xs font-semibold whitespace-nowrap opacity-0 group-hover:opacity-100 -translate-x-4 group-hover:translate-x-0 transition-all duration-300 ease-in-out pr-2">{{ $c('Ask Assistant', 'Assistant', 'المساعد') }}</span>
    </button>
@endsection

@push('scripts')
    @vite('resources/js/search-workspace.js')
@endpush
