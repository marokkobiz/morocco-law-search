@extends('layouts.app')

@section('title', __('landing.badge') . ' | MarocLoi')

@section('content')
    {{-- Hero Section --}}
    <section
        class="relative overflow-hidden bg-linear-to-br from-slate-900 via-blue-950 to-indigo-950 min-h-[calc(100vh-5rem)] flex items-center">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-40 -right-40 w-125 h-125 bg-blue-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-125 h-125 bg-indigo-500/15 rounded-full blur-3xl"></div>
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-200 h-200 bg-blue-600/5 rounded-full blur-3xl">
            </div>
        </div>
        <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
            style="background-image: linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px); background-size: 60px 60px;">
        </div>

        <div class="relative z-10 container-page pt-20 md:pt-24 pb-16 md:pb-24 w-full">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div data-animate="fade-up">
                    <span
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold tracking-wider uppercase bg-white/10 text-blue-200 border border-white/10 backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-blue-400 shadow-lg shadow-blue-400/50"></span>
                        {{ __('landing.badge') }}
                    </span>

                    <h1
                        class="mt-4 text-5xl sm:text-6xl md:text-7xl lg:text-8xl font-serif font-bold leading-none tracking-tight">
                        <span class="gradient-text">{!! __('landing.title_html') !!}</span>
                    </h1>

                    <p class="mt-6 text-lg sm:text-xl text-blue-100/80 max-w-2xl leading-relaxed font-sans">
                        {{ __('landing.subtitle') }}
                    </p>

                    <form
                        class="flex items-center gap-2 p-2 mt-8 bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl shadow-2xl shadow-blue-500/10 max-w-2xl"
                        action="/login" method="get">
                        <div class="flex items-center gap-3 flex-1 px-4">
                            <svg class="w-5 h-5 text-blue-300 shrink-0" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="search" name="q" placeholder="{{ __('landing.placeholder') }}"
                                class="w-full bg-transparent border-none text-white placeholder:text-blue-200/50 text-sm md:text-base font-medium focus:outline-none">
                        </div>
                        <button type="submit" class="btn-primary shrink-0">
                            {{ __('landing.search') }}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </button>
                    </form>
                </div>

                <div class="hidden lg:flex justify-center" data-animate="fade-up" style="--delay: 0.15s">
                    <img src="{{ asset('images/hero2.jpg') }}" alt="MarocLoi Dashboard Preview"
                        class="img-elevate w-full max-w-lg rounded-2xl shadow-2xl shadow-blue-900/40 ring-1 ring-white/10">
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-12 lg:mt-16" data-animate="fade-up">
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4" style="--delay: 0s">
                    <strong class="block text-2xl font-bold text-white">16,000+</strong>
                    <span
                        class="block mt-1 text-xs font-semibold tracking-wide text-blue-200/70 uppercase">{{ __('landing.stats_articles') }}</span>
                </div>
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4" style="--delay: 0.1s">
                    <strong class="block text-2xl font-bold text-white">200+</strong>
                    <span
                        class="block mt-1 text-xs font-semibold tracking-wide text-blue-200/70 uppercase">{{ __('landing.stats_sources') }}</span>
                </div>
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4" style="--delay: 0.2s">
                    <strong class="block text-2xl font-bold text-white">99.9%</strong>
                    <span
                        class="block mt-1 text-xs font-semibold tracking-wide text-blue-200/70 uppercase">{{ __('landing.stats_uptime') }}</span>
                </div>
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4" style="--delay: 0.3s">
                    <strong class="block text-2xl font-bold text-white">Real-time</strong>
                    <span
                        class="block mt-1 text-xs font-semibold tracking-wide text-blue-200/70 uppercase">{{ __('landing.stats_updates') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Sources Section --}}
    <section id="sources" class="py-20 md:py-28">
        <div class="container-page">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6" data-animate="fade-up">
                <div class="max-w-3xl">
                    <span class="section-label">{{ __('landing.sources_label') }}</span>
                    <h2 class="section-title mt-4">{{ __('landing.sources_title') }}</h2>
                    <p class="section-desc">{{ __('landing.sources_desc') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 mt-12">
                <article class="card card-hover p-6 md:p-8" data-animate="fade-up" style="--delay: 0s">
                    <div
                        class="w-12 h-12 rounded-xl bg-linear-to-br from-blue-500 to-blue-600 flex items-center justify-center mb-5 shadow-lg shadow-blue-500/20">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">{{ __('landing.source_card_1_title') }}</h3>
                    <p class="mt-3 text-gray-600 leading-relaxed">{{ __('landing.source_card_1_desc') }}</p>
                </article>

                <article class="card card-hover p-6 md:p-8" data-animate="fade-up" style="--delay: 0.1s">
                    <div
                        class="w-12 h-12 rounded-xl bg-linear-to-br from-blue-500 to-blue-600 flex items-center justify-center mb-5 shadow-lg shadow-blue-500/20">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">{{ __('landing.source_card_2_title') }}</h3>
                    <p class="mt-3 text-gray-600 leading-relaxed">{{ __('landing.source_card_2_desc') }}</p>
                </article>

                <article class="card card-hover p-6 md:p-8" data-animate="fade-up" style="--delay: 0.2s">
                    <div
                        class="w-12 h-12 rounded-xl bg-linear-to-br from-blue-500 to-blue-600 flex items-center justify-center mb-5 shadow-lg shadow-blue-500/20">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">{{ __('landing.source_card_3_title') }}</h3>
                    <p class="mt-3 text-gray-600 leading-relaxed">{{ __('landing.source_card_3_desc') }}</p>
                </article>
            </div>
        </div>
    </section>

    {{-- Coverage Section --}}
    <section id="coverage" class="py-20 md:py-28 bg-linear-to-b from-gray-50 to-white">
        <div class="container-page">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="hidden lg:flex justify-center" data-animate="fade-up">
                    <img src="{{ asset('/images/hero.jpg') }}" alt="Coverage Visualization"
                        class="img-elevate w-full max-w-md rounded-2xl shadow-lg shadow-blue-500/5 ring-1 ring-blue-100">
                </div>

                <div data-animate="fade-up" style="--delay: 0.15s">
                    <span class="section-label">{{ __('landing.coverage_label') }}</span>
                    <h2 class="section-title mt-4">{{ __('landing.coverage_title') }}</h2>
                    <p class="section-desc">{{ __('landing.coverage_desc') }}</p>

                    <div class="card p-6 md:p-8 border-blue-100 bg-linear-to-br from-blue-50/50 to-white mt-8">
                        <div class="space-y-5">
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shrink-0 mt-1">
                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <strong
                                        class="block text-gray-900 font-bold">{{ __('landing.coverage_item_1_title') }}</strong>
                                    <span
                                        class="text-sm text-gray-600 mt-1 block">{{ __('landing.coverage_item_1_desc') }}</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shrink-0 mt-1">
                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <strong
                                        class="block text-gray-900 font-bold">{{ __('landing.coverage_item_2_title') }}</strong>
                                    <span
                                        class="text-sm text-gray-600 mt-1 block">{{ __('landing.coverage_item_2_desc') }}</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shrink-0 mt-1">
                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <strong
                                        class="block text-gray-900 font-bold">{{ __('landing.coverage_item_3_title') }}</strong>
                                    <span
                                        class="text-sm text-gray-600 mt-1 block">{{ __('landing.coverage_item_3_desc') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </section>

    {{-- Database Section --}}
    <section id="database" class="py-20 md:py-28">
        <div class="container-page">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6" data-animate="fade-up">
                <div class="max-w-3xl">
                    <span class="section-label">{{ __('landing.database_label') }}</span>
                    <h2 class="section-title mt-4">{{ __('landing.database_title') }}</h2>
                    <p class="section-desc">{{ __('landing.database_desc') }}</p>
                </div>
                {{-- <div class="hidden lg:block shrink-0" data-animate="fade-in" style="--delay: 0.1s">
                    <img src="https://placehold.co/200x200/2563eb/dbeafe?text=Legal+DB" alt="Database"
                        class="img-elevate rounded-xl shadow-lg shadow-blue-500/10 ring-1 ring-blue-100/50">
                </div> --}}
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mt-12">
                <div class="card p-5 text-center hover:border-blue-200 transition-colors cursor-default"
                    data-animate="scale-in" style="--delay: 0s">
                    <div
                        class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center mx-auto font-bold text-sm">
                        BO</div>
                    <span class="block mt-3 text-sm font-semibold text-gray-800">{{ __('landing.source_bulletin') }}</span>
                </div>
                <div class="card p-5 text-center hover:border-emerald-200 transition-colors cursor-default"
                    data-animate="scale-in" style="--delay: 0.05s">
                    <div
                        class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto font-bold text-sm">
                        CT</div>
                    <span class="block mt-3 text-sm font-semibold text-gray-800">{{ __('landing.source_work_code') }}</span>
                </div>
                <div class="card p-5 text-center hover:border-rose-200 transition-colors cursor-default"
                    data-animate="scale-in" style="--delay: 0.1s">
                    <div
                        class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center mx-auto font-bold text-sm">
                        CP</div>
                    <span class="block mt-3 text-sm font-semibold text-gray-800">{{ __('landing.source_penal_code') }}</span>
                </div>
                <div class="card p-5 text-center hover:border-violet-200 transition-colors cursor-default"
                    data-animate="scale-in" style="--delay: 0.15s">
                    <div
                        class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center mx-auto font-bold text-sm">
                        CF</div>
                    <span class="block mt-3 text-sm font-semibold text-gray-800">{{ __('landing.source_family_code') }}</span>
                </div>
                <div class="card p-5 text-center hover:border-amber-200 transition-colors cursor-default"
                    data-animate="scale-in" style="--delay: 0.2s">
                    <div
                        class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center mx-auto font-bold text-sm">
                        DO</div>
                    <span class="block mt-3 text-sm font-semibold text-gray-800">{{ __('landing.source_doc') }}</span>
                </div>
                <div class="card p-5 text-center hover:border-cyan-200 transition-colors cursor-default"
                    data-animate="scale-in" style="--delay: 0.25s">
                    <div
                        class="w-10 h-10 rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center mx-auto font-bold text-sm">
                        IM</div>
                    <span class="block mt-3 text-sm font-semibold text-gray-800">{{ __('landing.source_real_estate') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA Section --}}
    <section class="relative overflow-hidden py-20 md:py-28 bg-gray-900">
        <div class="absolute inset-0">
            <img src="{{ asset('images/cta-background.jpg') }}" alt="" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-black/20"></div>
            <div class="absolute inset-0 bg-linear-to-t from-slate-900/95 via-slate-900/40 to-transparent"></div>
        </div>
        <div class="relative z-10 container-page text-center" data-animate="fade-up">
            <h2 class="text-3xl md:text-5xl font-serif font-bold text-white leading-tight">{{ __('landing.cta_title') }}
            </h2>
            <p class="mt-4 text-lg md:text-xl text-blue-100 max-w-2xl mx-auto">{{ __('landing.cta_desc') }}</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mt-10">
                <a href="{{ route('register') }}"
                    class="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-semibold text-blue-700 bg-white hover:bg-blue-50 shadow-2xl shadow-blue-900/30 transition-all duration-200 no-underline">
                    {{ __('landing.cta_create') }}
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </a>
                <a href="https://www.marokkobiz.com/"
                    class="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-semibold text-white border border-white/30 hover:bg-white/10 transition-all duration-200 no-underline">
                    {{ __('landing.cta_learn') }}
                </a>
            </div>
        </div>
    </section>
@endsection
