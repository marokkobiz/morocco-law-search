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

<body class="{{ $isArabic ? 'rtl' : '' }} bg-white font-sans text-gray-900 antialiased">
    <header class="sticky top-0 z-40 border-b border-gray-800 bg-gray-900">
        <div class="flex h-14 items-center justify-between gap-4 px-4 lg:px-6">
            <a href="/" class="flex shrink-0 items-center gap-2.5 no-underline">
                <img src="/icons/a.png" alt="MarocLoi" class="h-8 w-8 rounded-lg">
                <span class="text-sm font-bold text-white">Maroc<span
                        class="text-blue-400">Loi.com</span></span>
            </a>

            <div class="mx-auto hidden items-center gap-1 w-full justify-center md:flex">
                <div class="w-10"></div>
                <div class="flex flex-1 justify-center">
                    <a href="/#sources"
                        class="px-3 py-1.5 text-sm font-semibold text-gray-300 no-underline transition-colors hover:text-white">{{ $layoutCopy('sources') }}</a>
                    <a href="/#coverage"
                        class="px-3 py-1.5 text-sm font-semibold text-gray-300 no-underline transition-colors hover:text-white">{{ $layoutCopy('coverage') }}</a>
                    <a tabindex="-1" aria-disabled="true" href="{{ route('legal-aid') }}"
                        class="pointer-events-none cursor-not-allowed px-3 py-1.5 text-sm font-semibold text-gray-300 no-underline opacity-50 transition-colors hover:text-white">{{ $layoutCopy('legal_aid') }}</a>
                </div>
                <div>
                    <a href="https://web.facebook.com/profile.php?id=61590564394012"
                        target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-semibold text-gray-300 no-underline transition-colors hover:text-white">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        </span>
                        {{ $layoutCopy('customer-service') }}
                    </a>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2 md:gap-3">
                {{-- Language Switcher --}}
                <details class="relative">
                    <summary
                        class="cursor-pointer list-none rounded-lg border border-gray-700 bg-gray-800 px-3 py-2.5 text-xs font-bold text-gray-300 transition-colors hover:border-gray-600 hover:text-white [&::-webkit-details-marker]:hidden">
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
                        class="absolute z-50 mt-2 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-2 shadow-xl ltr:-right-4 rtl:-left-4">
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
                    {{-- Staff Panel Button (Admin or Advisor) - hidden on small, inside burger menu --}}
                    @if (Auth::user()->isAdmin() || Auth::user()->isAdvisor())
                        <a href="{{ Auth::user()->isAdmin() ? '/admin' : route('advisor.cases.index') }}"
                            class="hidden md:flex h-8 items-center gap-1.5 rounded-lg bg-amber-600 px-3.5 text-xs font-bold text-white no-underline shadow-sm transition-colors hover:bg-amber-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            @if (Auth::user()->isAdmin())
                                {{ $c('Admin Panel', 'Panneau Admin', 'لوحة الإدارة') }}
                            @else
                                {{ $c('Advisor Panel', 'Panneau Conseiller', 'لوحة المستشار') }}
                            @endif
                        </a>
                    @endif

                    <a href="{{ route('app.workspace') }}"
                        class="hidden md:flex h-8 items-center rounded-lg bg-blue-600 px-4 text-xs font-semibold text-white no-underline transition-colors hover:bg-blue-500">{{ $c('Explore Laws', 'Explorer les Lois', 'استكشف القوانين') }}</a>

                    {{-- Logout Button --}}
                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="hidden md:flex h-8 items-center gap-1.5 rounded-lg bg-gray-800 px-4 py-2.5 text-sm font-semibold text-gray-300 no-underline transition-colors hover:bg-gray-700 hover:text-white"
                        title="{{ $c('Logout', 'Déconnexion', 'تسجيل الخروج') }}">
                       Logout
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                @else
                    <a href="{{ route('login') }}"
                        class="hidden md:inline-flex h-8 items-center rounded-lg border border-white/30 px-4 py-1.5 text-xs font-semibold text-white no-underline transition-all duration-200 hover:bg-white/10">{{ $layoutCopy('login') }}</a>
                    <a href="{{ route('register') }}"
                        class="hidden md:flex h-8 items-center rounded-lg bg-blue-600 px-4 text-xs font-semibold text-white no-underline transition-colors hover:bg-blue-500">{{ $layoutCopy('start') }}</a>
                @endauth
                <!-- Burger button - very right -->
                <button type="button" id="navbar-burger" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navbar-mobile"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-700 bg-gray-800 text-gray-300 transition-colors hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-900 md:hidden">
                    <svg id="navbar-burger-icon-open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <svg id="navbar-burger-icon-close" class="hidden h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        <!-- Mobile burger menu -->
        <div id="navbar-mobile" class="hidden border-t border-gray-800 bg-gray-900 md:hidden">
            <nav class="flex flex-col gap-1 px-4 py-3">
                <a href="/#sources"
                    class="rounded-lg px-3 py-2.5 text-sm font-semibold text-gray-300 no-underline transition-colors hover:bg-gray-800 hover:text-white">{{ $layoutCopy('sources') }}</a>
                <a href="/#coverage"
                    class="rounded-lg px-3 py-2.5 text-sm font-semibold text-gray-300 no-underline transition-colors hover:bg-gray-800 hover:text-white">{{ $layoutCopy('coverage') }}</a>
                <a href="{{ route('legal-aid') }}"
                    class="rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white no-underline transition-colors hover:bg-blue-500">{{ $layoutCopy('legal_aid') }}</a>
                <a href="https://web.facebook.com/profile.php?id=61590564394012" target="_blank" rel="noopener noreferrer"
                    class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-gray-300 no-underline transition-colors hover:bg-gray-800 hover:text-white">
                    <span class="relative flex h-2.5 w-2.5 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    </span>
                    {{ $layoutCopy('customer-service') }}
                </a>
                @auth
                    <div class="mt-2 flex flex-col gap-2 border-t border-gray-800 pt-3">
                        @if (Auth::user()->isAdmin() || Auth::user()->isAdvisor())
                            <a href="{{ Auth::user()->isAdmin() ? '/admin' : route('advisor.cases.index') }}"
                                class="flex items-center justify-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-bold text-white no-underline shadow-sm transition-colors hover:bg-amber-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                @if (Auth::user()->isAdmin())
                                    {{ $c('Admin Panel', 'Panneau Admin', 'لوحة الإدارة') }}
                                @else
                                    {{ $c('Advisor Panel', 'Panneau Conseiller', 'لوحة المستشار') }}
                                @endif
                            </a>
                        @endif
                        <a href="{{ route('app.workspace') }}"
                            class="flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white no-underline transition-colors hover:bg-blue-500">{{ $c('Explore Laws', 'Explorer les Lois', 'استكشف القوانين') }}</a>
                        <a href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form-mobile').submit();"
                            class="flex items-center justify-center gap-1.5 rounded-lg bg-gray-800 px-4 py-2.5 text-sm font-semibold text-gray-300 no-underline transition-colors hover:bg-gray-700 hover:text-white">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            {{ $c('Logout', 'Déconnexion', 'تسجيل الخروج') }}
                        </a>
                        <form id="logout-form-mobile" action="{{ route('logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    </div>
                @else
                    <div class="mt-2 flex flex-col gap-2 border-t border-gray-800 pt-3">
                        <a href="{{ route('login') }}"
                            class="flex items-center justify-center rounded-lg border border-white/30 px-4 py-2.5 text-sm font-semibold text-white no-underline transition-colors hover:bg-white/10">{{ $layoutCopy('login') }}</a>
                        <a href="{{ route('register') }}"
                            class="flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white no-underline transition-colors hover:bg-blue-500">{{ $layoutCopy('start') }}</a>
                    </div>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="bg-gray-900 py-10">
        <div class="container-page">
            <p class="mb-8 text-center text-sm text-gray-400">{{ $layoutCopy('footer') }}</p>
            <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
                <div class="flex items-center gap-3">
                    <img src="/icons/a.png" alt="MarocLoi" class="h-8 w-8 rounded-lg opacity-80">
                    <span class="text-sm font-semibold text-gray-400">Maroc<span
                            class="text-gray-300">Loi.com</span></span>
                </div>
                <div>
                    <p class="text-center text-sm text-gray-500">Copyright Marokko Biz of 31.01.12 SARL</p>
                    <p class="mt-3 text-center text-xs text-slate-400">www.marocloi.com is part of Marokko Biz of
                        31.01.12 SARL</p>
                    <div class="text-center">
                        <div
                            class="flex flex-wrap items-center justify-center gap-x-1 text-[10px] text-slate-400 sm:text-xs">
                            <a href="https://www.de-bail.com" target="_blank" rel="noopener noreferrer"
                                class="no-underline transition-colors duration-200 hover:text-slate-500">
                                de-bail.com
                            </a>
                            <span class="select-none"> - </span>
                            <a href="https://www.marokkobiz.com" target="_blank" rel="noopener noreferrer"
                                class="no-underline transition-colors duration-200 hover:text-slate-500">
                                marokkobiz.com
                            </a>
                            <span class="select-none"> - </span>
                            <a href="https://www.marokkobiztv.com" target="_blank" rel="noopener noreferrer"
                                class="no-underline transition-colors duration-200 hover:text-slate-500">
                                marokkobiztv.com
                            </a>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-1 md:items-start">
                    <a href="https://marokkobiz.com/docs/privacy_policy.pdf" target="_blank"
                        class="text-sm text-gray-400 transition-colors hover:text-gray-200">{{ $layoutCopy('privacy') }}</a>
                    <a href="https://marokkobiz.com/docs/terms_and_conditions.pdf" target="_blank"
                        class="text-sm text-gray-400 transition-colors hover:text-gray-200">{{ $layoutCopy('terms') }}</a>
                    <a href="https://marokkobiz.com/" target="_blank"
                        class="text-sm text-gray-400 transition-colors hover:text-gray-200">{{ $layoutCopy('about') }}</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            var burger = document.getElementById('navbar-burger');
            var mobile = document.getElementById('navbar-mobile');
            var iconOpen = document.getElementById('navbar-burger-icon-open');
            var iconClose = document.getElementById('navbar-burger-icon-close');
            if (!burger || !mobile) return;
            function setOpen(open) {
                burger.setAttribute('aria-expanded', open ? 'true' : 'false');
                mobile.classList.toggle('hidden', !open);
                if (iconOpen) iconOpen.classList.toggle('hidden', open);
                if (iconClose) iconClose.classList.toggle('hidden', !open);
            }
            burger.addEventListener('click', function (e) {
                e.stopPropagation();
                var isHidden = mobile.classList.contains('hidden');
                setOpen(isHidden);
            });
            document.addEventListener('click', function (e) {
                if (!mobile.classList.contains('hidden') && !mobile.contains(e.target) && !burger.contains(e.target)) {
                    setOpen(false);
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !mobile.classList.contains('hidden')) setOpen(false);
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 768) setOpen(false);
            });
        })();
    </script>
    @stack('scripts')
</body>

</html>
