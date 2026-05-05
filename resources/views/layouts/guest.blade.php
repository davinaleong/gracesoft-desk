<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="GraceSoft Desk secure admin access for internal operations and finance workflows.">
    <meta name="theme-color" content="#0f172a">

    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased min-h-screen text-[#111322] bg-indigo-700">
    <div class="mx-auto grid min-h-screen w-full max-w-6xl items-center gap-8 px-4 py-8 lg:grid-cols-2 lg:px-8">
        <section class="hidden text-white lg:block">
            <img src="{{ asset('wm-w.svg') }}" alt="GraceSoft Desk logo" class="h-16 w-auto">
            <h2 class="mt-10 max-w-md font-serif text-4xl font-bold leading-tight">
                {{ __('Operations visibility, finance control, and reporting workflows in one place.') }}
            </h2>
            <p class="mt-4 max-w-lg text-indigo-100">
                {{ __('GraceSoft Desk keeps internal operations aligned across project tracking, time logs, and finance review.') }}
            </p>
        </section>

        <section class="rounded-2xl border border-[#d9deea] bg-white p-6 shadow-xl lg:p-8">
            <header class="mb-6 flex items-center justify-between gap-4">
                <img src="{{ asset('wm.svg') }}" alt="GraceSoft Desk logo" class="h-12 w-auto">
                <span
                    class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-indigo-700">{{ __('Secure Access') }}</span>
            </header>

            <main>
                {{ $slot }}
            </main>

            <footer class="mt-6 border-t border-[#d9deea] pt-4">
                <p class="text-xs text-[#5f6477]">&copy; {{ now()->year }}
                    {{ config('app.name', 'GraceSoft Desk') }}. {{ __('All rights reserved.') }}</p>
            </footer>
        </section>
    </div>
</body>

</html>
