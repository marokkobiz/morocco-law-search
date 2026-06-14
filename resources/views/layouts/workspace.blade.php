@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $c = fn($en, $fr, $ar) => $locale === 'fr' ? $fr : ($locale === 'ar' ? $ar : $en);
    $localeMeta = [
        'en' => ['code' => 'EN', 'label' => 'English'],
        'fr' => ['code' => 'FR', 'label' => 'Français'],
        'ar' => ['code' => 'AR', 'label' => 'العربية'],
    ];
    $activeLocale = $locale;
    $activeLocaleMeta = $localeMeta[$activeLocale];
    $localeOptions = $localeMeta;
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Workspace | MarocLoi')</title>
    <link rel="icon" href="/icons/a.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="font-sans antialiased bg-gray-50 text-gray-900 {{ $isRtl ? 'rtl' : '' }}">

    <header class="sticky top-0 z-40 bg-gray-900 border-b border-gray-800 select-none">
        <div class="flex items-center h-14 px-4 lg:px-6 gap-4">
            <a href="/" class="flex items-center gap-2.5 shrink-0 no-underline">
                <img src="/icons/a.png" alt="MarocLoi" class="w-8 h-8 rounded-lg">
                <span class="text-sm font-bold text-white hidden sm:inline">Maroc<span
                        class="text-blue-400">Loi</span></span>
            </a>

            <div class="flex-1 flex items-center justify-center relative max-w-xl mx-auto w-full">
                <form id="search-form" class="w-full flex items-center gap-2">
                    <div class="relative flex-1 flex items-center">
                        <svg class="absolute ltr:left-3 rtl:right-3 w-4 h-4 text-gray-500 pointer-events-none"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input id="search-input" type="search" autocomplete="off"
                            placeholder="{{ $c('Search laws, articles, references...', 'Recherchez lois, articles, références...', 'ابحث في القوانين والمواد والمراجع...') }}"
                            class="w-full h-9 bg-gray-800 border border-gray-700 ltr:pl-10 rtl:pr-10 ltr:pr-14 sm:ltr:pr-9 rtl:pl-14 sm:rtl:pl-9 rounded-lg text-sm text-gray-100 placeholder:text-gray-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                        <button id="clear-search" type="button"
                            class="hidden absolute ltr:right-9 sm:ltr:right-2 rtl:left-9 sm:rtl:left-2 w-5 h-5 rounded-full bg-gray-600 hover:bg-gray-500 flex items-center justify-center transition-colors cursor-pointer">
                            <svg class="w-3 h-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <button type="submit"
                            class="sm:hidden absolute ltr:right-1 rtl:left-1 w-7 h-7 bg-blue-600 hover:bg-blue-500 rounded-md flex items-center justify-center transition-colors cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>
                    </div>
                    <button type="submit"
                        class="hidden sm:inline-flex h-9 px-4 items-center bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors cursor-pointer shrink-0">
                        {{ $c('Search', 'Rechercher', 'بحث') }}
                    </button>
                </form>
                <div id="suggestions-panel"
                    class="hidden absolute top-full ltr:left-0 rtl:right-0 mt-1 w-full bg-white rounded-xl border border-gray-200 shadow-xl overflow-hidden z-50">
                </div>
            </div>

            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                @auth
                    {{-- Desktop language switcher --}}
                    <details class="hidden lg:block relative">
                        <summary
                            class="list-none cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-xs font-bold text-gray-300 transition-colors hover:border-gray-600 hover:text-white [&::-webkit-details-marker]:hidden">
                            <span class="inline-flex items-center gap-2">
                                <span class="uppercase">{{ $activeLocaleMeta['code'] }}</span>
                                <svg class="h-3.5 w-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 9l6 6 6-6"></path>
                                </svg>
                            </span>
                            <span class="sr-only">Select language</span>
                        </summary>
                        <div class="absolute ltr:-right-4 rtl:-left-4 z-50 mt-2 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-2 shadow-xl">
                            @foreach ($localeOptions as $localeOption => $localeMeta)
                                <a href="{{ route('locale.switch', $localeOption) }}"
                                @class([
                                    'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold transition-colors',
                                    'bg-blue-50 text-blue-700' => $activeLocale === $localeOption,
                                    'text-gray-700 hover:bg-gray-50' => $activeLocale !== $localeOption,
                                ])>
                                    <span class="min-w-7 rounded bg-gray-100 px-1.5 py-0.5 text-center text-xs font-black uppercase text-gray-600">{{ $localeMeta['code'] }}</span>
                                    <span>{{ $localeMeta['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                    <details class="relative">
                        <summary
                            class="list-none cursor-pointer w-8 h-8 rounded-md bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-300 hover:bg-gray-600 transition-colors [&::-webkit-details-marker]:hidden">
                            {{ substr(Auth::user()->name ?? Auth::user()->email, 0, 1) }}
                        </summary>
                        <div class="absolute ltr:right-0 rtl:left-0 z-50 mt-2 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white py-2 shadow-xl">
                            <div class="px-4 py-2.5 border-b border-gray-100">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ Auth::user()->name ?? '' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
                            </div>
                            <div class="lg:hidden px-4 py-2 border-b border-gray-100">
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">{{ $c('Language', 'Langue', 'اللغة') }}</p>
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                {{ $c('Logout', 'Déconnexion', 'تسجيل الخروج') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('login') }}"
                        class="text-xs font-semibold text-gray-400 hover:text-gray-200 transition-colors no-underline">{{ $c('Login', 'Connexion', 'تسجيل الدخول') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <div class="flex overflow-hidden" style="height: calc(100vh - 3.6rem);">
        @yield('workspace-content')
    </div>

    @stack('scripts')
</body>

</html>
