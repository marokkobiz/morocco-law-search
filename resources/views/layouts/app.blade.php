@php
  $interfaceLanguage = request('lang');
  $interfaceLanguage = in_array($interfaceLanguage, ['en', 'fr', 'ar'], true) ? $interfaceLanguage : 'ar';
  $isArabic = $interfaceLanguage === 'ar';
  $localeMeta = [
    'en' => ['code' => 'EN', 'label' => 'English'],
    'fr' => ['code' => 'FR', 'label' => 'Français'],
    'ar' => ['code' => 'AR', 'label' => 'العربية'],
  ];
  $activeLocale = $interfaceLanguage;
  $activeLocaleMeta = $localeMeta[$activeLocale];
  $localeOptions = $localeMeta;
  $layoutCopy = [
    'en' => ['about' => 'About', 'sources' => 'Sources', 'coverage' => 'Coverage', 'login' => 'Login', 'start' => 'Get Started', 'footer' => 'Legal information from indexed sources. Not a substitute for legal advice.', 'privacy' => 'Privacy policy', 'terms' => 'Terms and conditions'],
    'fr' => ['about' => 'A propos', 'sources' => 'Sources', 'coverage' => 'Couverture', 'login' => 'Connexion', 'start' => 'Commencer', 'footer' => 'Information juridique issue de sources indexees. Ne remplace pas un avis juridique.', 'privacy' => 'Politique de confidentialite', 'terms' => 'Conditions generales'],
    'ar' => ['about' => 'من نحن', 'sources' => 'المصادر', 'coverage' => 'التغطية', 'login' => 'تسجيل الدخول', 'start' => 'ابدأ الآن', 'footer' => 'معلومات قانونية من مصادر مفهرسة. لا تغني عن استشارة قانونية.', 'privacy' => 'سياسة الخصوصية', 'terms' => 'الشروط والأحكام'],
  ][$interfaceLanguage];
@endphp
<!doctype html>
<html lang="{{ $interfaceLanguage }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">
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
      rel="stylesheet"
    >
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
  </head>
  <body class="font-sans antialiased text-gray-900 bg-white {{ $isArabic ? 'rtl' : '' }}">
    <nav class="nav-glass">
      <div class="container-page">
        <div class="flex items-center justify-between h-16 md:h-20">
            <a href="/?lang={{ $interfaceLanguage }}" class="flex items-center gap-3 font-bold text-gray-900 no-underline">
            <img src="/icons/a.png" alt="MarocLoi" class="w-9 h-9 rounded-xl shadow-md shadow-blue-500/20">
            <span class="text-sm md:text-base">Maroc<span class="text-blue-600">Loi</span></span>
          </a>

          <div class="hidden md:flex items-center gap-1">
            <a href="https://www.marokkobiz.com/" class="nav-link">{{ $layoutCopy['about'] }}</a>
            <a href="#sources" class="nav-link">{{ $layoutCopy['sources'] }}</a>
            <a href="#coverage" class="nav-link">{{ $layoutCopy['coverage'] }}</a>
          </div>

          <div class="flex items-center gap-3">
            <details class="relative">
              <summary
                class="list-none cursor-pointer rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm transition-colors hover:border-blue-200 hover:bg-blue-50/50 [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-2">
                  <span class="uppercase">{{ $activeLocaleMeta['code'] }}</span>
                  <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 9l6 6 6-6">
                    </path>
                  </svg>
                </span>
                <span class="sr-only">Select language</span>
              </summary>
              <div
                class="absolute ltr:-right-20 rtl:-left-20 z-50 mt-2 w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-2 shadow-xl">
                @foreach ($localeOptions as $localeOption => $localeMeta)
                  <a href="{{ request()->fullUrlWithQuery(['lang' => $localeOption]) }}"
                    @class([
                      'flex items-center gap-3 px-4 py-3 text-sm font-semibold transition-colors',
                      'bg-blue-50 text-blue-700' => $activeLocale === $localeOption,
                      'text-slate-700 hover:bg-slate-50' => $activeLocale !== $localeOption,
                    ])>
                    <span
                      class="min-w-7 rounded bg-slate-100 px-1.5 py-0.5 text-center text-xs font-black uppercase text-slate-700">{{ $localeMeta['code'] }}</span>
                    <span>{{ $localeMeta['label'] }}</span>
                  </a>
                @endforeach
              </div>
            </details>
            @auth
              <a href="/app?lang={{ $interfaceLanguage }}" class="btn-primary-sm no-underline">{{ $interfaceLanguage === 'fr' ? 'Ouvrir l application' : ($interfaceLanguage === 'ar' ? 'فتح التطبيق' : 'Open app') }}</a>
            @else
              <a href="/login?lang={{ $interfaceLanguage }}" class="hidden sm:inline-flex text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors no-underline">{{ $layoutCopy['login'] }}</a>
              <a href="/register?lang={{ $interfaceLanguage }}" class="btn-primary-sm no-underline">{{ $layoutCopy['start'] }}</a>
            @endauth
          </div>
        </div>
      </div>
    </nav>

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
          <p class="text-sm text-gray-500 text-center">Copyright Marokko Biz of 31.01.12 SARL</p>
          <div class="flex flex-col items-center md:items-end gap-1">
            <a href="https://www.marokkobiz.com/privacy-policy" class="text-sm text-gray-400 hover:text-gray-200 transition-colors">{{ $layoutCopy['privacy'] }}</a>
            <a href="https://www.marokkobiz.com/terms-and-conditions" class="text-sm text-gray-400 hover:text-gray-200 transition-colors">{{ $layoutCopy['terms'] }}</a>
          </div>
        </div>
      </div>
    </footer>

    @stack('scripts')
  </body>
</html>
