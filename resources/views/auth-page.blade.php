<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mode === 'register' ? 'Create account' : 'Login' }} | Marokko Biz Law OS</title>
    <link rel="icon" href="/marokko-biz-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,650;9..144,760&family=Manrope:wght@400;500;600;700;800;900&display=swap"
      rel="stylesheet"
    >
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body class="font-sans antialiased">
    <main class="blue-account-page">
      <a href="/" class="blue-account-brand">
        <img src="/marokko-biz-icon.png" alt="Marokko Biz">
        <span>Marokko Biz Law OS</span>
      </a>

      <section class="blue-account-card">
        <p>{{ $mode === 'register' ? 'Platform access' : 'Welcome back' }}</p>
        <h1>{{ $mode === 'register' ? 'Create account' : 'Login' }}</h1>
        <span>Account access is prepared for the legal research platform.</span>

        <form action="/app">
          @if ($mode === 'register')
            <label>
              Full name
              <input type="text" placeholder="Your name">
            </label>
          @endif
          <label>
            Work email
            <input type="email" placeholder="name@company.com">
          </label>
          <label>
            Password
            <input type="password" placeholder="Password">
          </label>
          <button type="submit">{{ $mode === 'register' ? 'Create account' : 'Login' }}</button>
        </form>

        <a href="{{ $mode === 'register' ? '/login' : '/register' }}">
          {{ $mode === 'register' ? 'Already have an account? Login' : 'Need access? Create account' }}
        </a>
      </section>
    </main>
  </body>
</html>
