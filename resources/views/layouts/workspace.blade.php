@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $c = fn($en, $fr, $ar) => $locale === 'fr' ? $fr : ($locale === 'ar' ? $ar : $en);
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

    <header class="sticky top-0 z-40 bg-gray-900 border-b border-gray-800">
        <div class="flex items-center h-14 px-4 lg:px-6 gap-4">
            <a href="/" class="flex items-center gap-2.5 shrink-0 no-underline">
                <img src="/icons/a.png" alt="MarocLoi" class="w-8 h-8 rounded-lg">
                <span class="text-sm font-bold text-white hidden sm:inline">Maroc<span
                        class="text-blue-400">Loi</span></span>
            </a>

            <div class="flex-1 flex items-center justify-center relative max-w-xl mx-auto w-full">
                <form id="search-form" class="w-full flex items-center gap-2">
                    <div class="relative flex-1 items-center">
                        <svg class="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pointer-events-none"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input id="search-input" type="search" autocomplete="off"
                            placeholder="{{ $c('Search laws, articles, references...', 'Recherchez lois, articles, références...', 'ابحث في القوانين والمواد والمراجع...') }}"
                            class="w-full h-9 bg-gray-800 border border-gray-700 ltr:pl-10 rtl:pr-10 ltr:pr-4 rtl:pl-4 rounded-lg text-sm text-gray-100 placeholder:text-gray-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                    </div>
                    <button type="submit"
                        class="h-9 px-4 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors cursor-pointer shrink-0">
                        {{ $c('Search', 'Rechercher', 'بحث') }}
                    </button>
                </form>
                <div id="suggestions-panel"
                    class="hidden absolute top-full ltr:left-0 rtl:right-0 mt-1 w-full bg-white rounded-xl border border-gray-200 shadow-xl overflow-hidden z-50">
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                @auth
                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="text-xs font-semibold text-gray-400 hover:text-gray-200 transition-colors no-underline">
                        {{ $c('Logout', 'Déconnexion', 'تسجيل الخروج') }}
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                    <div
                        class="w-7 h-7 rounded-md bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-300">
                        {{ substr(Auth::user()->name ?? Auth::user()->email, 0, 1) }}
                    </div>
                @else
                    <a href="{{ route('login') }}"
                        class="text-xs font-semibold text-gray-400 hover:text-gray-200 transition-colors no-underline">{{ $c('Login', 'Connexion', 'تسجيل الدخول') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <div class="flex overflow-hidden h-[90vh]">
        @yield('workspace-content')
    </div>

    @stack('scripts')
</body>

</html>
