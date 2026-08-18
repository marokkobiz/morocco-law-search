@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $isArabic = $locale === 'ar';
    $c = fn($en, $fr, $ar) => $locale === 'fr' ? $fr : ($locale === 'ar' ? $ar : $en);
    $localeMeta = [
        'en' => ['code' => 'EN', 'label' => 'English'],
        'fr' => ['code' => 'FR', 'label' => 'Français'],
        'ar' => ['code' => 'AR', 'label' => 'العربية'],
    ];
    $activeLocale = $locale;
    $activeLocaleMeta = $localeMeta[$activeLocale];
    $localeOptions = $localeMeta;
    $layoutCopy = fn($key) => __("layout.$key");
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Moroccan Legal Research | MarocLoi')</title>
    <link rel="icon" href="/icons/a.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="font-sans antialiased text-gray-900 bg-white {{ $isArabic ? 'rtl' : '' }}">
    <header class="sticky top-0 z-40 bg-gray-900 border-b border-gray-800">
        <div class="flex items-center h-14 px-4 lg:px-6 gap-4">
            <a href="/" class="flex items-center gap-2.5 shrink-0 no-underline">
                <img src="/icons/a.png" alt="MarocLoi" class="w-8 h-8 rounded-lg">
                <span class="text-sm font-bold text-white hidden sm:inline">Maroc<span
                        class="text-blue-400">Loi.com</span></span>
            </a>

            <div class="hidden md:flex items-center gap-1 mx-auto">
                <a href="https://www.marokkobiz.com/"
                    class="px-3 py-1.5 text-sm font-semibold text-gray-300 hover:text-white transition-colors no-underline">{{ $layoutCopy('about') }}</a>
                <a href="/#sources"
                    class="px-3 py-1.5 text-sm font-semibold text-gray-300 hover:text-white transition-colors no-underline">{{ $layoutCopy('sources') }}</a>
                <a href="/#coverage"
                    class="px-3 py-1.5 text-sm font-semibold text-gray-300 hover:text-white transition-colors no-underline">{{ $layoutCopy('coverage') }}</a>
                @if (Route::is('test') || Route::is('legal-aid'))
                    <a href="{{ route('legal-aid') }}"
                        class="px-3 py-1.5 text-sm font-semibold text-gray-300 hover:text-white transition-colors no-underline">{{ $layoutCopy('legal_aid') }}</a>
                @endif
            </div>

            <div class="flex items-center gap-3 shrink-0">
                {{-- Language Switcher --}}
                <details class="relative">
                    <summary
                        class="list-none cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-xs font-bold text-gray-300 transition-colors hover:border-gray-600 hover:text-white [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-2">
                            <span class="uppercase">{{ $activeLocaleMeta['code'] }}</span>
                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                    d="M6 9l6 6 6-6"></path>
                            </svg>
                        </span>
                        <span class="sr-only">Select language</span>
                    </summary>
                    <div
                        class="absolute ltr:-right-4 rtl:-left-4 z-50 mt-2 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-2 shadow-xl">
                        @foreach ($localeOptions as $localeOption => $localeMeta)
                            <a href="{{ route('locale.switch', $localeOption) }}" @class([
                                'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold transition-colors',
                                'bg-blue-50 text-blue-700' => $activeLocale === $localeOption,
                                'text-gray-700 hover:bg-gray-50' => $activeLocale !== $localeOption,
                            ])>
                                <span
                                    class="min-w-7 rounded bg-gray-100 px-1.5 py-0.5 text-center text-xs font-black uppercase text-gray-600">{{ $localeMeta['code'] }}</span>
                                <span>{{ $localeMeta['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>

                @auth
                    {{-- Admin Panel Button (visible only to admins) --}}
                    @if (Auth::user()->is_admin || Auth::user()->role === 'admin' || (method_exists(Auth::user(), 'isAdmin') && Auth::user()->isAdmin()))
                        <a href="/admin"
                            class="h-8 flex items-center px-3.5 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-lg transition-colors no-underline gap-1.5 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            {{ $c('Admin Panel', 'Panneau Admin', 'لوحة الإدارة') }}
                        </a>
                    @endif

                    <a href="{{ route('app.workspace') }}"
                        class="h-8 flex items-center px-4 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition-colors no-underline">{{ $locale === 'fr' ? 'Dashboard' : ($locale === 'ar' ? 'لوحة التحكم' : 'Dashboard') }}</a>

                    {{-- Logout Button --}}
                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="h-8 flex items-center px-3 bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white text-xs font-semibold rounded-lg transition-colors no-underline"
                        title="{{ $c('Logout', 'Déconnexion', 'تسجيل الخروج') }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                @else
                    <a href="{{ route('login') }}"
                        class="inline-flex items-center text-xs h-8 px-4 py-1.5 rounded-lg font-semibold text-white border border-white/30 hover:bg-white/10 transition-all duration-200 no-underline">{{ $layoutCopy('login') }}</a>
                    <a href="{{ route('register') }}"
                        class="h-8 flex items-center px-4 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition-colors no-underline">{{ $layoutCopy('start') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="bg-gray-900 py-10">
        <div class="container-page">
            <p class="text-sm text-gray-400 text-center mb-8">{{ $layoutCopy('footer') }}</p>
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="/icons/a.png" alt="MarocLoi" class="w-8 h-8 rounded-lg opacity-80">
                    <span class="text-sm font-semibold text-gray-400">Maroc<span class="text-gray-300">Loi.com</span></span>
                </div>
                <div>
                    <p class="text-sm text-gray-500 text-center">Copyright Marokko Biz of 31.01.12 SARL</p>
                    <p class="mt-3 text-xs text-slate-400 text-center">www.marocloi.com is part of Marokko Biz of 31.01.12 SARL</p>
                    <p class="text-xs text-slate-400 text-center lg:whitespace-nowrap">Marokko Biz of 31.01.12 SARL, Lot 9 Rue, 10 Dziri V Montagne 90000 Tangier Patent 127407, ICE 003067038000038, CNSS 5800935</p>
                    <p class="text-xs text-slate-400 text-center">Email: <a href="mailto:info@marocloi.com" class="text-slate-300 hover:text-white">info@marocloi.com</a></p>
                    <div class="text-center">
                        <div
                            class="flex flex-wrap items-center justify-center gap-x-1 text-[10px] sm:text-xs text-slate-400">
                            <a href="https://www.de-bail.com" target="_blank" rel="noopener noreferrer"
                                class="hover:text-slate-500 transition-colors duration-200 no-underline">
                                de-bail.com
                            </a>
                            <span class="select-none"> - </span>
                            <a href="https://www.marokkobiz.com" target="_blank" rel="noopener noreferrer"
                                class="hover:text-slate-500 transition-colors duration-200 no-underline">
                                marokkobiz.com
                            </a>
                            <span class="select-none"> - </span>
                            <a href="https://www.marokkobiztv.com" target="_blank" rel="noopener noreferrer"
                                class="hover:text-slate-500 transition-colors duration-200 no-underline">
                                marokkobiztv.com
                            </a>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-center md:items-end gap-1">
                    <a href="http://marokkobiz.com/docs/privacy_policy.pdf" target="_blank"
                        class="text-sm text-gray-400 hover:text-gray-200 transition-colors">{{ $layoutCopy('privacy') }}</a>
                    <a href="http://marokkobiz.com/docs/terms_and_conditions.pdf" target="_blank"
                        class="text-sm text-gray-400 hover:text-gray-200 transition-colors">{{ $layoutCopy('terms') }}</a>
                </div>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>

</html>
