@php
  $pageLanguage = in_array(request('lang'), ['en', 'fr', 'ar'], true) ? request('lang') : 'en';
@endphp
<!doctype html>
<html lang="{{ $pageLanguage }}" dir="{{ $pageLanguage === 'ar' ? 'rtl' : 'ltr' }}">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | Marokko Biz Law OS</title>
    <link rel="icon" href="/icons/a.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap"
      rel="stylesheet"
    >
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body class="font-sans antialiased">
    <main class="auth-page">
      <a href="/" class="auth-brand">
        <img src="/icons/a.png" alt="Marokko Biz">
        <span>Marokko Biz Law OS</span>
      </a>
      @yield('content')
    </main>
  </body>
</html>
