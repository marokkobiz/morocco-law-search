@php
  $interfaceLanguage = request('lang');
  $interfaceLanguage = in_array($interfaceLanguage, ['en', 'fr', 'ar'], true) ? $interfaceLanguage : 'ar';
  $isArabic = $interfaceLanguage === 'ar';
  $layoutCopy = [
    'en' => ['about' => 'About', 'sources' => 'Sources', 'coverage' => 'Coverage', 'login' => 'Login', 'start' => 'Get Started', 'footer' => 'Legal information from indexed sources. Not a substitute for legal advice.'],
    'fr' => ['about' => 'A propos', 'sources' => 'Sources', 'coverage' => 'Couverture', 'login' => 'Connexion', 'start' => 'Commencer', 'footer' => 'Information juridique issue de sources indexees. Ne remplace pas un avis juridique.'],
    'ar' => ['about' => 'من نحن', 'sources' => 'المصادر', 'coverage' => 'التغطية', 'login' => 'تسجيل الدخول', 'start' => 'ابدأ الآن', 'footer' => 'معلومات قانونية من مصادر مفهرسة. لا تغني عن استشارة قانونية.'],
  ][$interfaceLanguage];
@endphp
<!doctype html>
<html lang="{{ $interfaceLanguage }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Moroccan Legal Research | Marokko Biz')</title>
    <link rel="icon" href="/icons/a.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap"
      rel="stylesheet"
    >
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body class="font-sans antialiased text-gray-900 bg-white {{ $isArabic ? 'rtl' : '' }}">
    <nav class="nav-glass">
      <div class="container-page">
        <div class="flex items-center justify-between h-16 md:h-20">
            <a href="/?lang={{ $interfaceLanguage }}" class="flex items-center gap-3 font-bold text-gray-900 no-underline">
            <img src="/icons/a.png" alt="Marokko Biz" class="w-9 h-9 rounded-xl shadow-md shadow-blue-500/20">
            <span class="text-sm md:text-base">Marokko<span class="text-blue-600">Biz</span></span>
          </a>

          <div class="hidden md:flex items-center gap-1">
            <a href="https://www.marokkobiz.com/" class="nav-link">{{ $layoutCopy['about'] }}</a>
            <a href="#sources" class="nav-link">{{ $layoutCopy['sources'] }}</a>
            <a href="#coverage" class="nav-link">{{ $layoutCopy['coverage'] }}</a>
          </div>

          <div class="flex items-center gap-3">
            <div class="hidden sm:flex items-center rounded-full border border-blue-100 bg-blue-50/80 p-1 text-xs font-bold text-blue-700">
              @foreach (['en' => 'EN', 'fr' => 'FR', 'ar' => 'AR'] as $code => $label)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="px-2.5 py-1 rounded-full no-underline transition {{ $interfaceLanguage === $code ? 'bg-white text-blue-700 shadow-sm' : 'text-blue-500 hover:text-blue-700' }}">{{ $label }}</a>
              @endforeach
            </div>
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
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <img src="/icons/a.png" alt="Marokko Biz" class="w-8 h-8 rounded-lg opacity-80">
            <span class="text-sm font-semibold text-gray-400">Marokko<span class="text-gray-300">Biz</span></span>
          </div>
          <p class="text-sm text-gray-500 text-center">{{ $layoutCopy['footer'] }}</p>
        </div>
      </div>
    </footer>

    @stack('scripts')
  </body>
</html>
