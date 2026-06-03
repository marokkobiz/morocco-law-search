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
            Your application is ready.
        </main>
    </body>
</html>
