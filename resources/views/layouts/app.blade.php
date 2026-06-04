<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Moroccan Legal Research | Marokko Biz')</title>
    <link rel="icon" href="/marokko-biz-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap"
      rel="stylesheet"
    >
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body class="font-sans antialiased text-gray-900 bg-white">
    <nav class="nav-glass">
      <div class="container-page">
        <div class="flex items-center justify-between h-16 md:h-20">
          <a href="/" class="flex items-center gap-3 font-bold text-gray-900 no-underline">
            <img src="/marokko-biz-icon.png" alt="Marokko Biz" class="w-9 h-9 rounded-xl shadow-md shadow-blue-500/20">
            <span class="text-sm md:text-base">Marokko<span class="text-blue-600">Biz</span></span>
          </a>

          <div class="hidden md:flex items-center gap-1">
            <a href="https://www.marokkobiz.com/" class="nav-link">About</a>
            <a href="#sources" class="nav-link">Sources</a>
            <a href="#coverage" class="nav-link">Coverage</a>
          </div>

          <div class="flex items-center gap-3">
            <a href="/login" class="hidden sm:inline-flex text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors no-underline">Login</a>
            <a href="/register" class="btn-primary-sm no-underline">Get Started</a>
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
            <img src="/marokko-biz-icon.png" alt="Marokko Biz" class="w-8 h-8 rounded-lg opacity-80">
            <span class="text-sm font-semibold text-gray-400">Marokko<span class="text-gray-300">Biz</span></span>
          </div>
          <p class="text-sm text-gray-500 text-center">Legal information from indexed sources. Not a substitute for legal advice.</p>
        </div>
      </div>
    </footer>

    @stack('scripts')
  </body>
</html>
