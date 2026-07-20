<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | MarocLoi Admin</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">

<div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white">

        <div class="px-6 py-6 border-b border-slate-800">
            <h1 class="text-2xl font-bold">
                Maroc<span class="text-blue-400">Loi</span>
            </h1>

            <p class="text-sm text-gray-400 mt-1">
                Administration
            </p>
        </div>

        <nav class="mt-8">

            <a href="{{ route('admin.dashboard') }}"
               class="block px-6 py-3 hover:bg-slate-800">

                Dashboard

            </a>

        </nav>

    </aside>


    <!-- Main -->

    <main class="flex-1">

        <header class="bg-white border-b">

            <div class="px-8 py-5 flex justify-between items-center">

                <div>

                    <h2 class="text-3xl font-bold">
                        @yield('page-title')
                    </h2>

                    <p class="text-gray-500">
                        @yield('page-description')
                    </p>

                </div>

                <div class="text-right">

                    <div class="font-semibold">
                        {{ auth()->user()->name }}
                    </div>

                    <div class="text-sm text-gray-500">
                        {{ auth()->user()->email }}
                    </div>

                </div>

            </div>

        </header>

        <section class="p-8">

            @yield('content')

        </section>

    </main>

</div>

</body>
</html>