<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Morocco Law Search') }}</title>
        <meta name="description" content="Morocco Law Search is preparing for launch.">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900">
        <main class="flex min-h-screen items-center justify-center px-6 py-10">
            <section class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white px-8 py-14 text-center shadow-sm sm:px-12 sm:py-16">
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                    Morocco Law Search
                </p>

                <h1 class="mt-6 text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl">
                    Coming soon
                </h1>

                <p class="mt-4 text-base leading-7 text-slate-600 sm:text-lg">
                    We are building the first version of the platform and preparing everything for launch.
                </p>
            </section>
        </main>
    </body>
</html>
