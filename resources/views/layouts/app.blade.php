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
    $layoutCopy = [
        'en' => [
            'about' => 'About',
            'sources' => 'Sources',
            'coverage' => 'Coverage',
            'login' => 'Login',
            'start' => 'Get Started',
            'footer' => 'Legal information from indexed sources. Not a substitute for legal advice.',
            'privacy' => 'Privacy policy',
            'terms' => 'Terms and conditions',
        ],
        'fr' => [
            'about' => 'A propos',
            'sources' => 'Sources',
            'coverage' => 'Couverture',
            'login' => 'Connexion',
            'start' => 'Commencer',
            'footer' => 'Information juridique issue de sources indexees. Ne remplace pas un avis juridique.',
            'privacy' => 'Politique de confidentialite',
            'terms' => 'Conditions generales',
        ],
        'ar' => [
            'about' => 'من نحن',
            'sources' => 'المصادر',
            'coverage' => 'التغطية',
            'login' => 'تسجيل الدخول',
            'start' => 'ابدأ الآن',
            'footer' => 'معلومات قانونية من مصادر مفهرسة. لا تغني عن استشارة قانونية.',
            'privacy' => 'سياسة الخصوصية',
            'terms' => 'الشروط والأحكام',
        ],
    ][$locale];
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
                        class="text-blue-400">Loi</span></span>
            </a>

            <div class="hidden md:flex items-center gap-1 mx-auto">
                <a href="https://www.marokkobiz.com/"
                    class="px-3 py-1.5 text-sm font-semibold text-gray-300 hover:text-white transition-colors no-underline">{{ $layoutCopy['about'] }}</a>
                <a href="#sources"
                    class="px-3 py-1.5 text-sm font-semibold text-gray-300 hover:text-white transition-colors no-underline">{{ $layoutCopy['sources'] }}</a>
                <a href="#coverage"
                    class="px-3 py-1.5 text-sm font-semibold text-gray-300 hover:text-white transition-colors no-underline">{{ $layoutCopy['coverage'] }}</a>
            </div>

            <div class="flex items-center gap-3 shrink-0">
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
                    <a href="{{ route('app.workspace') }}"
                        class="h-8 flex items-center px-4 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition-colors no-underline">{{ $locale === 'fr' ? 'Dashboard' : ($locale === 'ar' ? 'لوحة التحكم' : 'Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}"
                        class="inline-flex items-center text-xs h-8 px-4 py-1.5 rounded-lg font-semibold text-white border border-white/30 hover:bg-white/10 transition-all duration-200 no-underline">{{ $layoutCopy['login'] }}</a>
                    <a href="{{ route('register') }}"
                        class="h-8 flex items-center px-4 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition-colors no-underline">{{ $layoutCopy['start'] }}</a>
                @endauth
            </div>

            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                @auth
                    <details class="relative">
                        <summary
                            class="list-none cursor-pointer w-8 h-8 rounded-md bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-300 hover:bg-gray-600 transition-colors [&::-webkit-details-marker]:hidden">
                            {{ substr(Auth::user()->name ?? Auth::user()->email, 0, 1) }}
                        </summary>
                        <div
                            class="absolute ltr:right-0 rtl:left-0 z-50 mt-2 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white py-2 shadow-xl">
                            <div class="px-4 py-2.5 border-b border-gray-100">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ Auth::user()->name ?? '' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
                            </div>
                            <div class="lg:hidden px-4 py-2 border-b border-gray-100">
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                                    {{ $c('Language', 'Langue', 'اللغة') }}</p>
                                <div class="flex gap-1">
                                    @foreach ($localeOptions as $localeOption => $localeMeta)
                                        <a href="{{ route('locale.switch', $localeOption) }}"
                                            class="flex-1 flex items-center justify-center gap-1 px-2 py-1.5 rounded-lg text-xs font-semibold no-underline transition-colors {{ $activeLocale === $localeOption ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-200' : 'text-gray-500 hover:bg-gray-100' }}">
                                            <span>{{ $localeMeta['code'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                            <a href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors no-underline">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                {{ $c('Logout', 'Déconnexion', 'تسجيل الخروج') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf
                            </form>
                        </div>
                    </details>
                @endauth
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="bg-gray-900 py-10">
        <div class="container-page">
            <p class="text-sm text-gray-400 text-center mb-8">{{ $layoutCopy['footer'] }}</p>
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="/icons/a.png" alt="MarocLoi" class="w-8 h-8 rounded-lg opacity-80">
                    <span class="text-sm font-semibold text-gray-400">Maroc<span class="text-gray-300">Loi</span></span>
                </div>
                <div>
                    <p class="text-sm text-gray-500 text-center">Copyright Marokko Biz of 31.01.12 SARL</p>
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
                    <a href="https://www.marokkobiz.com/privacy-policy"
                        class="text-sm text-gray-400 hover:text-gray-200 transition-colors">{{ $layoutCopy['privacy'] }}</a>
                    <a href="https://www.marokkobiz.com/terms-and-conditions"
                        class="text-sm text-gray-400 hover:text-gray-200 transition-colors">{{ $layoutCopy['terms'] }}</a>
                </div>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>

</html>
