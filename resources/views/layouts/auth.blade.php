@php
    $pageLanguage = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $pageLanguage }}" dir="{{ $pageLanguage === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | MarocLoi</title>
    <link rel="icon" href="/icons/a.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="font-sans text-slate-800 antialiased">
    <div class="flex h-screen overflow-hidden">

        {{-- Right: Form --}}

        <div class="hidden lg:flex lg:w-1/2 relative items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-black/20"></div>
            <img src="/images/auth.jpg" alt="" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-linear-to-t from-slate-900/95 via-slate-900/40 to-transparent"></div>
        </div>

        {{-- Left: Image / Branding --}}

        <div class="flex-1 flex flex-col bg-slate-50">
            <div class="flex items-center justify-center pt-4 sm:pt-6 px-4">
                <p class="text-center text-sm text-slate-500">
                    <a href="{{ route('landing') }}"
                        class="inline-flex items-center hover:text-slate-700 transition-colors">
                        <svg class="h-4 w-4 ltr:mr-2 rtl:ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        @if ($pageLanguage === 'fr')
                            Retour à l'accueil
                        @elseif ($pageLanguage === 'ar')
                            العودة إلى الرئيسية
                        @else
                            Back to Home
                        @endif
                    </a>
                </p>
            </div>

            <div class="flex-1 flex items-center justify-center overflow-y-hidden px-4 sm:px-6 lg:px-8">
                <div class="w-full max-w-xl">
                    @yield('content')
                </div>
            </div>
        </div>


    </div>

    @stack('scripts')
</body>

</html>
