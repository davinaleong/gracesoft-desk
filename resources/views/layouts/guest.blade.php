<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="GraceSoft Desk secure admin access for internal operations and finance workflows.">
    <meta name="theme-color" content="#0f172a">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-slate-900 antialiased">
    <div
        class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 px-4 py-10 sm:px-6 lg:px-8">
        <div class="pointer-events-none absolute -top-20 -left-12 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -right-10 h-72 w-72 rounded-full bg-sky-400/20 blur-3xl">
        </div>

        <div class="relative mx-auto flex min-h-[calc(100vh-5rem)] w-full max-w-5xl items-center justify-center">
            <div
                class="grid w-full overflow-hidden rounded-3xl border border-white/10 bg-white/95 shadow-2xl lg:grid-cols-5">
                <aside
                    class="hidden bg-slate-900 px-8 py-10 text-slate-100 lg:col-span-2 lg:flex lg:flex-col lg:justify-between">
                    <div>
                        <a href="/" class="inline-flex items-center">
                            <x-application-logo class="h-10 w-auto fill-current text-white" />
                        </a>
                        <p class="mt-10 text-xs uppercase tracking-[0.24em] text-blue-200/80">Internal Operations</p>
                        <h2 class="mt-3 font-serif text-3xl leading-tight text-white">Secure Access Desk</h2>
                        <p class="mt-4 text-sm leading-7 text-slate-300">
                            Sign in to access finance controls, project visibility, and reporting operations.
                        </p>
                    </div>

                    <p class="font-mono text-xs uppercase tracking-[0.14em] text-slate-400">GraceSoft Desk</p>
                </aside>

                <main class="px-6 py-8 sm:px-10 sm:py-10 lg:col-span-3">
                    <div class="mb-8 lg:hidden">
                        <a href="/" class="inline-flex items-center">
                            <x-application-logo class="h-10 w-auto fill-current text-slate-800" />
                        </a>
                    </div>

                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>
</body>

</html>
