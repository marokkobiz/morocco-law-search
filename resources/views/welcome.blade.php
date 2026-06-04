<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts: Refined pairing — Playfair Display (display) + DM Sans (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
    <style>
        /* ============================================================
           DESIGN TOKENS — edit here, consistent everywhere
        ============================================================ */
        :root {
            --blue-50:  #eff6ff;
            --blue-100: #dbeafe;
            --blue-200: #bfdbfe;
            --blue-400: #60a5fa;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --blue-800: #1e40af;
            --blue-900: #1e3a8a;
            --blue-950: #172554;

            --ink-50:  #f8fafc;
            --ink-100: #f1f5f9;
            --ink-200: #e2e8f0;
            --ink-300: #cbd5e1;
            --ink-400: #94a3b8;
            --ink-500: #64748b;
            --ink-600: #475569;
            --ink-700: #334155;
            --ink-800: #1e293b;
            --ink-900: #0f172a;

            --gold:    #f59e0b;
            --gold-lt: #fde68a;

            --font-display: 'Playfair Display', Georgia, serif;
            --font-body:    'DM Sans', system-ui, sans-serif;

            --radius-card: 20px;
            --radius-btn:  10px;

            --shadow-card: 0 4px 24px -4px rgba(30,58,138,.12), 0 1px 4px -1px rgba(30,58,138,.08);
            --shadow-glow: 0 0 40px -8px rgba(59,130,246,.35);
        }

        /* ============================================================
           RESET & BASE
        ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background: var(--ink-900);
            color: var(--ink-100);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ============================================================
           REUSABLE UTILITY CLASSES (Tailwind-style but custom tokens)
        ============================================================ */

        /* --- Buttons --- */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            font-family: var(--font-body);
            font-size: .875rem;
            font-weight: 500;
            letter-spacing: .02em;
            border-radius: var(--radius-btn);
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: all .22s cubic-bezier(.4,0,.2,1);
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-sm  { padding: .45rem 1.1rem; font-size: .8rem; }
        .btn-md  { padding: .6rem 1.5rem; }
        .btn-lg  { padding: .85rem 2.2rem; font-size: 1rem; font-weight: 600; }

        .btn-primary {
            background: linear-gradient(135deg, var(--blue-600) 0%, var(--blue-700) 100%);
            color: #fff;
            border-color: var(--blue-700);
            box-shadow: 0 2px 12px -2px rgba(37,99,235,.45);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--blue-500) 0%, var(--blue-600) 100%);
            box-shadow: 0 4px 20px -4px rgba(37,99,235,.65);
            transform: translateY(-1px);
        }
        .btn-primary:active { transform: translateY(0); }

        .btn-ghost {
            background: transparent;
            color: var(--ink-300);
            border-color: rgba(255,255,255,.12);
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,.06);
            color: #fff;
            border-color: rgba(255,255,255,.22);
        }

        .btn-outline {
            background: transparent;
            color: var(--blue-400);
            border-color: var(--blue-700);
        }
        .btn-outline:hover {
            background: rgba(59,130,246,.1);
            border-color: var(--blue-500);
            color: var(--blue-300);
        }

        /* --- Cards --- */
        .card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: var(--radius-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .card-hover {
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-card);
            border-color: rgba(59,130,246,.25);
        }

        /* --- Badges --- */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: .28rem .7rem;
            border-radius: 999px;
            border: 1px solid;
        }
        .badge-blue {
            background: rgba(59,130,246,.12);
            color: var(--blue-300);
            border-color: rgba(59,130,246,.25);
        }

        /* --- Divider --- */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.1) 50%, transparent);
        }

        /* --- Section title --- */
        .section-eyebrow {
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--blue-400);
        }
        .section-title {
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 600;
            line-height: 1.15;
            color: #fff;
        }
        .section-subtitle {
            font-size: 1.05rem;
            font-weight: 300;
            color: var(--ink-400);
            line-height: 1.7;
        }

        /* ============================================================
           BACKGROUND — layered gradient mesh
        ============================================================ */
        .bg-scene {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 70% -10%, rgba(37,99,235,.28) 0%, transparent 60%),
                radial-gradient(ellipse 50% 50% at -5% 60%, rgba(29,78,216,.20) 0%, transparent 55%),
                radial-gradient(ellipse 60% 40% at 100% 100%, rgba(30,58,138,.22) 0%, transparent 55%),
                linear-gradient(160deg, var(--ink-900) 0%, #0c1829 50%, var(--ink-900) 100%);
        }
        /* subtle grid overlay */
        .bg-scene::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 40%, transparent 100%);
        }

        /* floating orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            animation: float-orb 8s ease-in-out infinite alternate;
        }
        .orb-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(37,99,235,.18) 0%, transparent 70%);
            top: -100px; right: -80px;
            animation-delay: 0s;
        }
        .orb-2 {
            width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(29,78,216,.15) 0%, transparent 70%);
            bottom: 10%; left: -60px;
            animation-delay: -3s;
        }
        @keyframes float-orb {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(20px, 30px) scale(1.06); }
        }

        /* ============================================================
           LAYOUT
        ============================================================ */
        .page-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ============================================================
           NAVBAR
        ============================================================ */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.4rem 2rem;
            position: relative;
        }
        .navbar::after {
            content: '';
            position: absolute;
            bottom: 0; left: 2rem; right: 2rem;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.08) 50%, transparent);
        }

        .navbar-brand {
            font-family: var(--font-display);
            font-size: 1.35rem;
            font-weight: 600;
            color: #fff;
            text-decoration: none;
            letter-spacing: -.02em;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .navbar-brand-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--blue-500);
            box-shadow: 0 0 10px var(--blue-500);
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        /* ============================================================
           HERO
        ============================================================ */
        .hero {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
            padding: 3rem 2rem 2rem;
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
        }

        .hero-content { max-width: 560px; }

        .hero-eyebrow {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.4rem;
        }
        .eyebrow-line {
            width: 28px; height: 1.5px;
            background: linear-gradient(90deg, var(--blue-500), var(--blue-300));
            border-radius: 2px;
        }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(2.4rem, 4.5vw, 3.8rem);
            font-weight: 600;
            line-height: 1.1;
            color: #fff;
            letter-spacing: -.025em;
            margin-bottom: 1.4rem;
        }
        .hero-title .accent {
            background: linear-gradient(135deg, var(--blue-400) 0%, #93c5fd 60%, var(--blue-200) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-title .italic {
            font-style: italic;
            font-weight: 400;
        }

        .hero-description {
            font-size: 1.05rem;
            font-weight: 300;
            color: var(--ink-400);
            line-height: 1.75;
            margin-bottom: 2.2rem;
            max-width: 440px;
        }

        .hero-cta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* ============================================================
           ARTWORK PANEL (right side)
        ============================================================ */
        .artwork-panel {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .artwork-frame {
            position: relative;
            width: 100%;
            max-width: 540px;
            border-radius: var(--radius-card);
            overflow: hidden;
            border: 1px solid rgba(59,130,246,.2);
            box-shadow: var(--shadow-glow), var(--shadow-card);
        }
        .artwork-frame::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(37,99,235,.12) 0%, rgba(30,58,138,.08) 100%);
            z-index: 1;
            pointer-events: none;
        }
        .artwork-bg {
            background: linear-gradient(135deg, #0d1f42 0%, #0f172a 50%, #111827 100%);
            padding: 2.5rem 2rem 0;
        }

        /* corner glow accents */
        .artwork-frame::after {
            content: '';
            position: absolute;
            top: -1px; left: -1px; right: -1px;
            height: 2px;
            background: linear-gradient(90deg, transparent 10%, var(--blue-500) 50%, transparent 90%);
            border-radius: 2px 2px 0 0;
            z-index: 2;
        }

        /* the laravel logo svg */
        .artwork-logo {
            position: relative;
            z-index: 1;
        }
        .artwork-logo svg {
            display: block;
            width: 100%;
        }

        /* geometric artwork */
        .artwork-geo {
            position: relative;
            z-index: 1;
            margin: 0 -2rem;
        }

        /* ============================================================
           STEPS / QUICKSTART
        ============================================================ */
        .steps-section {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem 3rem;
            width: 100%;
        }

        .steps-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 1.8rem;
            gap: 1rem;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .step-card {
            padding: 1.4rem 1.6rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .step-number {
            flex-shrink: 0;
            width: 34px; height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue-700), var(--blue-900));
            border: 1px solid rgba(59,130,246,.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
            font-weight: 700;
            color: var(--blue-300);
            box-shadow: 0 0 12px -4px rgba(59,130,246,.3);
        }

        .step-content {}
        .step-label {
            font-size: .92rem;
            font-weight: 500;
            color: var(--ink-100);
            margin-bottom: .3rem;
        }
        .step-desc {
            font-size: .82rem;
            font-weight: 300;
            color: var(--ink-500);
            line-height: 1.5;
        }

        /* ============================================================
           LINK — inline
        ============================================================ */
        .link-blue {
            color: var(--blue-400);
            text-decoration: none;
            font-weight: 500;
            position: relative;
            transition: color .18s;
        }
        .link-blue::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 0; right: 100%;
            height: 1px;
            background: var(--blue-400);
            transition: right .22s ease;
        }
        .link-blue:hover { color: var(--blue-300); }
        .link-blue:hover::after { right: 0; }

        /* ============================================================
           FOOTER
        ============================================================ */
        .footer {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.4rem 2rem;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .footer::before {
            content: none;
        }
        .footer-text {
            font-size: .78rem;
            color: var(--ink-600);
            letter-spacing: .02em;
        }
        .footer-version {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* ============================================================
           ANIMATIONS
        ============================================================ */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .anim-fade-up { animation: fade-up .7s cubic-bezier(.22,1,.36,1) both; }
        .delay-1 { animation-delay: .1s; }
        .delay-2 { animation-delay: .22s; }
        .delay-3 { animation-delay: .34s; }
        .delay-4 { animation-delay: .46s; }
        .delay-5 { animation-delay: .58s; }

        @keyframes scale-in {
            from { opacity: 0; transform: scale(.96); }
            to   { opacity: 1; transform: scale(1); }
        }
        .anim-scale-in { animation: scale-in .65s cubic-bezier(.22,1,.36,1) both; }

        /* logo text shimmer */
        @keyframes shimmer {
            0%   { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1024px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
                padding-top: 2rem;
            }
            .hero-content { max-width: 100%; }
            .hero-description { max-width: 100%; }
            .hero-eyebrow { justify-content: center; }
            .hero-cta { justify-content: center; }
            .artwork-panel { order: -1; }
            .artwork-frame { max-width: 460px; margin: 0 auto; }
            .steps-grid { grid-template-columns: 1fr; }
            .steps-header { flex-direction: column; align-items: flex-start; }
        }

        @media (max-width: 640px) {
            .navbar { padding: 1rem 1.25rem; }
            .navbar-brand { font-size: 1.1rem; }
            .hero { padding: 1.5rem 1.25rem 1.5rem; gap: 2rem; }
            .hero-title { font-size: 2.1rem; }
            .steps-section { padding: 0 1.25rem 2rem; }
            .footer { flex-direction: column; text-align: center; gap: .6rem; padding: 1.2rem 1.25rem; }
            .artwork-bg { padding: 1.5rem 1rem 0; }
        }
    </style>
    @endif
</head>
<body>

    <!-- ─── Background Scene ─────────────────────────────── -->
    <div class="bg-scene">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>

    <!-- ─── Page ─────────────────────────────────────────── -->
    <div class="page-wrapper">

        <!-- NAVBAR -->
        <nav class="navbar anim-fade-up">
            <a href="/" class="navbar-brand">
                <span class="navbar-brand-dot"></span>
                {{ config('app.name', 'Laravel') }}
            </a>

            @if (Route::has('login'))
            <div class="navbar-actions">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-sm">
                        Dashboard
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                            <path d="M2 6h8M6 2l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Log in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Get started</a>
                    @endif
                @endauth
            </div>
            @endif
        </nav>

        <!-- HERO -->
        <section class="hero">

            <!-- Left — Content -->
            <div class="hero-content">
                <div class="hero-eyebrow anim-fade-up delay-1">
                    <div class="eyebrow-line"></div>
                    <span class="badge badge-blue">v11 — Now available</span>
                </div>

                <h1 class="hero-title anim-fade-up delay-2">
                    Build <span class="accent italic">beautiful</span><br>
                    things with<br>
                    <span class="accent">Laravel</span>
                </h1>

                <p class="hero-description anim-fade-up delay-3">
                    The PHP framework for web artisans. Expressive, elegant syntax that delivers outstanding developer experience out of the box.
                </p>

                <div class="hero-cta anim-fade-up delay-4">
                    <a href="https://cloud.laravel.com" target="_blank" class="btn btn-primary btn-lg">
                        Deploy now
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M8 2.5L13 8l-5 5.5M3 8h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <a href="https://laravel.com/docs" target="_blank" class="btn btn-outline btn-lg">
                        Documentation
                    </a>
                </div>
            </div>

            <!-- Right — Artwork -->
            <div class="artwork-panel anim-scale-in delay-2">
                <div class="artwork-frame">
                    <div class="artwork-bg">

                        <!-- Laravel wordmark -->
                        <div class="artwork-logo" style="padding: 0 .5rem .5rem;">
                            <svg viewBox="0 0 438 104" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:#3b82f6;">
                                <path d="M17.2036 -3H0V102.197H49.5189V86.7187H17.2036V-3Z" fill="currentColor"/>
                                <path d="M110.256 41.6337C108.061 38.1275 104.945 35.3731 100.905 33.3681C96.8667 31.3647 92.8016 30.3618 88.7131 30.3618C83.4247 30.3618 78.5885 31.3389 74.201 33.2923C69.8111 35.2456 66.0474 37.928 62.9059 41.3333C59.7643 44.7401 57.3198 48.6726 55.5754 53.1293C53.8287 57.589 52.9572 62.274 52.9572 67.1813C52.9572 72.1925 53.8287 76.8995 55.5754 81.3069C57.3191 85.7173 59.7636 89.6241 62.9059 93.0293C66.0474 96.4361 69.8119 99.1155 74.201 101.069C78.5885 103.022 83.4247 103.999 88.7131 103.999C92.8016 103.999 96.8667 102.997 100.905 100.994C104.945 98.9911 108.061 96.2359 110.256 92.7282V102.195H126.563V32.1642H110.256V41.6337ZM108.76 75.7472C107.762 78.4531 106.366 80.8078 104.572 82.8112C102.776 84.8161 100.606 86.4183 98.0637 87.6206C95.5202 88.823 92.7004 89.4238 89.6103 89.4238C86.5178 89.4238 83.7252 88.823 81.2324 87.6206C78.7388 86.4183 76.5949 84.8161 74.7998 82.8112C73.004 80.8078 71.6319 78.4531 70.6856 75.7472C69.7356 73.0421 69.2644 70.1868 69.2644 67.1821C69.2644 64.1758 69.7356 61.3205 70.6856 58.6154C71.6319 55.9102 73.004 53.5571 74.7998 51.5522C76.5949 49.5495 78.738 47.9451 81.2324 46.7427C83.7252 45.5404 86.5178 44.9396 89.6103 44.9396C92.7012 44.9396 95.5202 45.5404 98.0637 46.7427C100.606 47.9451 102.776 49.5487 104.572 51.5522C106.367 53.5571 107.762 55.9102 108.76 58.6154C109.756 61.3205 110.256 64.1758 110.256 67.1821C110.256 70.1868 109.756 73.0421 108.76 75.7472Z" fill="currentColor"/>
                                <path d="M242.805 41.6337C240.611 38.1275 237.494 35.3731 233.455 33.3681C229.416 31.3647 225.351 30.3618 221.262 30.3618C215.974 30.3618 211.138 31.3389 206.75 33.2923C202.36 35.2456 198.597 37.928 195.455 41.3333C192.314 44.7401 189.869 48.6726 188.125 53.1293C186.378 57.589 185.507 62.274 185.507 67.1813C185.507 72.1925 186.378 76.8995 188.125 81.3069C189.868 85.7173 192.313 89.6241 195.455 93.0293C198.597 96.4361 202.361 99.1155 206.75 101.069C211.138 103.022 215.974 103.999 221.262 103.999C225.351 103.999 229.416 102.997 233.455 100.994C237.494 98.9911 240.611 96.2359 242.805 92.7282V102.195H259.112V32.1642H242.805V41.6337ZM241.31 75.7472C240.312 78.4531 238.916 80.8078 237.122 82.8112C235.326 84.8161 233.156 86.4183 230.614 87.6206C228.07 88.823 225.251 89.4238 222.16 89.4238C219.068 89.4238 216.275 88.823 213.782 87.6206C211.289 86.4183 209.145 84.8161 207.35 82.8112C205.554 80.8078 204.182 78.4531 203.236 75.7472C202.286 73.0421 201.814 70.1868 201.814 67.1821C201.814 64.1758 202.286 61.3205 203.236 58.6154C204.182 55.9102 205.554 53.5571 207.35 51.5522C209.145 49.5495 211.288 47.9451 213.782 46.7427C216.275 45.5404 219.068 44.9396 222.16 44.9396C225.251 44.9396 228.07 45.5404 230.614 46.7427C233.156 47.9451 235.326 49.5487 237.122 51.5522C238.917 53.5571 240.312 55.9102 241.31 58.6154C242.306 61.3205 242.806 64.1758 242.806 67.1821C242.805 70.1868 242.305 73.0421 241.31 75.7472Z" fill="currentColor"/>
                                <path d="M438 -3H421.694V102.197H438V-3Z" fill="currentColor"/>
                                <path d="M139.43 102.197H155.735V48.2834H183.712V32.1665H139.43V102.197Z" fill="currentColor"/>
                                <path d="M324.49 32.1665L303.995 85.794L283.498 32.1665H266.983L293.748 102.197H314.242L341.006 32.1665H324.49Z" fill="currentColor"/>
                                <path d="M376.571 30.3656C356.603 30.3656 340.797 46.8497 340.797 67.1828C340.797 89.6597 356.094 104 378.661 104C391.29 104 399.354 99.1488 409.206 88.5848L398.189 80.0226C398.183 80.031 389.874 90.9895 377.468 90.9895C363.048 90.9895 356.977 79.3111 356.977 73.269H411.075C413.917 50.1328 398.775 30.3656 376.571 30.3656ZM357.02 61.0967C357.145 59.7487 359.023 43.3761 376.442 43.3761C393.861 43.3761 395.978 59.7464 396.099 61.0967H357.02Z" fill="currentColor"/>
                            </svg>
                        </div>

                        <!-- Geometric artwork — kept from original, recolored for blue theme -->
                        <div class="artwork-geo">
                            <svg viewBox="0 0 440 320" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;width:100%;">
                                <!-- Base shapes -->
                                <g opacity=".9">
                                    <path d="M188.263 295.73L188.595 295.73C195.441 288.845 205.766 279.761 219.569 268.477C232.93 257.193 242.978 248.205 249.714 241.511C256.34 234.626 260.867 227.358 263.296 219.708C265.725 212.058 264.565 204.121 259.816 195.896C254.516 186.716 247.062 179.352 237.454 173.805C227.957 168.067 217.908 165.198 207.307 165.198C196.927 165.197 190.136 167.97 186.934 173.516C183.621 178.872 184.726 186.331 190.247 195.894L125.647 195.891C116.371 179.825 112.395 165.481 113.72 152.858C115.265 140.235 121.559 130.481 132.602 123.596C143.754 116.52 158.607 112.982 177.159 112.983C196.594 112.984 215.863 116.523 234.968 123.6C253.961 130.486 271.299 140.241 286.98 152.864C302.661 165.488 315.14 179.833 324.416 195.899C333.03 210.817 336.841 223.918 335.847 235.203C335.075 246.487 331.376 256.336 324.75 264.751C318.346 273.167 308.408 283.494 294.936 295.734L377.094 295.737L405.917 345.656L217.087 345.649L188.263 295.73Z" fill="rgba(30,64,175,0.5)" stroke="rgba(59,130,246,0.5)" stroke-width="1"/>
                                    <path d="M9.11884 166.339L-13.7396 166.338L-42.7286 116.132L43.0733 116.135L175.595 345.649L112.651 345.647L9.11884 166.339Z" fill="rgba(30,64,175,0.5)" stroke="rgba(59,130,246,0.5)" stroke-width="1"/>
                                </g>
                                <!-- Mid layer - yellow/gold tones replaced with lighter blue -->
                                <g opacity=".75">
                                    <path d="M204.592 267.449L204.923 267.449C211.769 260.564 222.094 251.479 235.897 240.196C249.258 228.912 259.306 219.923 266.042 213.23C272.668 206.345 277.195 199.077 279.624 191.427C282.053 183.777 280.893 175.839 276.145 167.615C270.844 158.435 263.39 151.071 253.782 145.524C244.285 139.786 234.236 136.917 223.635 136.916C213.255 136.916 206.464 139.689 203.262 145.235C199.949 150.59 201.054 158.049 206.575 167.612L141.975 167.61C132.699 151.544 128.723 137.2 130.048 124.577C131.593 111.954 137.887 102.2 148.93 95.3148C160.083 88.2388 174.935 84.7008 193.487 84.7018C212.922 84.7028 232.192 88.2418 251.296 95.3188C270.289 102.205 287.627 111.96 303.308 124.583C318.989 137.207 331.468 151.552 340.745 167.618C349.358 182.536 353.169 195.637 352.175 206.921C351.403 218.205 347.704 228.055 341.078 236.47C334.674 244.885 324.736 255.213 311.264 267.453L393.422 267.456L422.246 317.375L233.415 317.368L204.592 267.449Z" fill="rgba(37,99,235,0.4)" stroke="rgba(96,165,250,0.4)" stroke-width="1"/>
                                    <path d="M25.447 138.058L2.58852 138.057L-26.4005 87.851L59.4015 87.854L191.923 317.368L128.979 317.365L25.447 138.058Z" fill="rgba(37,99,235,0.4)" stroke="rgba(96,165,250,0.4)" stroke-width="1"/>
                                </g>
                                <!-- Top layer - glow blue -->
                                <g style="mix-blend-mode:screen" opacity=".6">
                                    <path d="M217.342 245.363L217.673 245.363C224.519 238.478 234.844 229.393 248.647 218.11C262.008 206.826 272.056 197.837 278.792 191.144C285.418 184.259 289.945 176.991 292.374 169.341C294.803 161.691 293.643 153.753 288.895 145.529C283.594 136.349 276.14 128.985 266.532 123.438C257.035 117.7 246.986 114.831 236.385 114.83C226.005 114.83 219.214 117.603 216.012 123.149C212.699 128.504 213.804 135.963 219.325 145.527L154.725 145.524C145.449 129.458 141.473 115.114 142.798 102.491C144.343 89.8678 150.637 80.1138 161.68 73.2288C172.833 66.1528 187.685 62.6148 206.237 62.6158C225.672 62.6168 244.942 66.1558 264.046 73.2328C283.039 80.1188 300.377 89.8738 316.058 102.497C331.739 115.121 344.218 129.466 353.495 145.532C362.108 160.45 365.919 173.551 364.925 184.835C364.153 196.12 360.454 205.969 353.828 214.384C347.424 222.799 337.486 233.127 324.014 245.367L406.172 245.37L434.996 295.289L246.165 295.282L217.342 245.363Z" fill="rgba(96,165,250,0.35)" stroke="rgba(147,197,253,0.4)" stroke-width="1"/>
                                    <path d="M38.197 115.972L15.3385 115.971L-13.6505 65.7648L72.1515 65.768L204.673 295.282L141.729 295.279L38.197 115.972Z" fill="rgba(96,165,250,0.35)" stroke="rgba(147,197,253,0.4)" stroke-width="1"/>
                                </g>
                                <!-- Wireframe detail overlay -->
                                <g opacity=".35">
                                    <path d="M246.544 194.79L246.875 194.79C253.722 187.905 264.046 178.82 277.849 167.537C291.21 156.253 301.259 147.264 307.995 140.57C314.62 133.685 319.147 126.418 321.577 118.768C324.006 111.117 322.846 103.18 318.097 94.956C312.796 85.775 305.342 78.412 295.735 72.865C286.238 67.127 276.189 64.258 265.588 64.257C255.208 64.257 248.416 67.03 245.214 72.576C241.902 77.931 243.006 85.39 248.528 94.953L183.928 94.951C174.652 78.885 170.676 64.541 172 51.918C173.546 39.2946 179.84 29.5408 190.882 22.6558C202.035 15.5798 216.887 12.042 235.439 12.043C254.874 12.044 274.144 15.583 293.248 22.66C312.242 29.546 329.579 39.301 345.261 51.924C360.942 64.548 373.421 78.892 382.697 94.958C391.311 109.877 395.121 122.978 394.128 134.262C393.355 145.546 389.656 155.396 383.031 163.811C376.627 172.226 366.688 182.554 353.217 194.794L435.375 194.797L464.198 244.716L275.367 244.709L246.544 194.79Z" stroke="rgba(147,197,253,0.6)" stroke-width="1" fill="none"/>
                                    <path d="M67.41 65.402L44.5515 65.401L15.5625 15.1953L101.364 15.1985L233.886 244.712L170.942 244.71L67.41 65.402Z" stroke="rgba(147,197,253,0.6)" stroke-width="1" fill="none"/>
                                </g>
                            </svg>
                        </div>

                    </div>
                </div>
            </div>

        </section>

        <!-- DIVIDER -->
        <div style="max-width:1280px;margin:0 auto;padding:0 2rem;width:100%;">
            <div class="divider"></div>
        </div>

        <!-- QUICKSTART STEPS -->
        <section class="steps-section" style="padding-top:2rem;">
            <div class="steps-header">
                <div>
                    <p class="section-eyebrow anim-fade-up delay-1" style="margin-bottom:.4rem;">Quick start</p>
                    <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:600;color:#fff;" class="anim-fade-up delay-2">Start building in minutes</h2>
                </div>
                <a href="https://laracasts.com" target="_blank" class="btn btn-ghost btn-sm anim-fade-up delay-3" style="flex-shrink:0;">
                    Watch tutorials
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                        <path d="M10.5 6.5V10.5H1.5V1.5H5.5M8 1.5h2.5M10.5 1.5L5 7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>

            <div class="steps-grid">
                <div class="card card-hover step-card anim-fade-up delay-2">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <p class="step-label">Read the Documentation</p>
                        <p class="step-desc">Comprehensive guides covering everything from installation to advanced features.
                            <a href="https://laravel.com/docs" target="_blank" class="link-blue" style="margin-left:.2rem;">laravel.com/docs ↗</a>
                        </p>
                    </div>
                </div>

                <div class="card card-hover step-card anim-fade-up delay-3">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <p class="step-label">Watch Video Tutorials</p>
                        <p class="step-desc">Hundreds of high-quality lessons at
                            <a href="https://laracasts.com" target="_blank" class="link-blue">Laracasts ↗</a>
                            — from beginner to expert.
                        </p>
                    </div>
                </div>

                <div class="card card-hover step-card anim-fade-up delay-4">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <p class="step-label">Deploy to the Cloud</p>
                        <p class="step-desc">Go live in minutes with
                            <a href="https://cloud.laravel.com" target="_blank" class="link-blue">Laravel Cloud ↗</a>
                            — zero-config server management.
                        </p>
                    </div>
                </div>

                <div class="card card-hover step-card anim-fade-up delay-5">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <p class="step-label">Join the Community</p>
                        <p class="step-desc">Connect with tens of thousands of developers at
                            <a href="https://laravel-news.com" target="_blank" class="link-blue">Laravel News ↗</a>
                            and beyond.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="footer">
            <p class="footer-text">© {{ date('Y') }} {{ config('app.name', 'Laravel') }}. Built with ♥ for artisans.</p>
            <div class="footer-version">
                <span class="badge badge-blue" style="font-size:.7rem;">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}</span>
                <span class="badge badge-blue" style="font-size:.7rem;">Laravel {{ app()->version() }}</span>
            </div>
        </footer>

    </div><!-- end page-wrapper -->

</body>
</html>