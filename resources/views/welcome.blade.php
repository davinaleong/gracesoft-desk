<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="GraceSoft Desk restricted access portal.">
    <meta name="theme-color" content="#4f46e5">

    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">

    <title>{{ config('app.name', 'GraceSoft Desk') }} | Restricted Access</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                margin: 0;
                background: #eef2ff;
                color: #0f172a;
            }
        </style>
    @endif
</head>

<body class="min-h-screen bg-[#eef2ff] text-[#111322] grid place-items-center antialiased">
    <main class="mx-auto max-w-4xl items-center space-y-4 gap-8">
        <section class="rounded-2xl border border-indigo-200 bg-white p-7 shadow-lg lg:p-10">
            <header class="flex items-center gap-4">
                <img src="{{ asset('wm.svg') }}" alt="GraceSoft Desk" class="h-11 w-auto">
                <span
                    class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-indigo-700">
                    Internal Portal
                </span>
            </header>

            <div class="mt-8 space-y-5">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-indigo-700">Restricted Access</p>
                <h1 class="font-serif text-4xl font-bold leading-tight text-[#111322] lg:text-5xl">
                    This system is for authorized GraceSoft personnel only.
                </h1>
                <p class="max-w-xl text-base leading-relaxed text-slate-700">
                    You are viewing a protected operations environment. Access attempts are monitored and audited.
                    If you do not have approved credentials, please close this page now.
                </p>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:ring-offset-2">
                        Continue to Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:ring-offset-2">
                        Sign In to Continue
                    </a>
                @endauth
                <a href="https://gracesoft.app"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:ring-offset-2">
                    Leave This Page
                </a>
            </div>

            <p class="mt-8 text-xs text-slate-500">
                Unauthorized use may result in access blocks and incident escalation.
            </p>
        </section>

        <aside
            class="relative overflow-hidden rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-700 via-indigo-600 to-indigo-500 p-7 text-white shadow-lg lg:p-10">
            <div class="absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/15 blur-2xl"></div>
            <div class="absolute -bottom-14 -left-10 h-44 w-44 rounded-full bg-violet-300/20 blur-3xl"></div>

            <div class="relative space-y-6">
                <h2 class="font-serif text-2xl font-bold leading-tight">Security Notice</h2>

                <ul class="flex gap-4 text-sm leading-relaxed text-indigo-50 p-0">
                    <li class="rounded-lg bg-white/10 px-3 py-2">
                        All sessions require password and two-factor authentication.
                    </li>
                    <li class="rounded-lg bg-white/10 px-3 py-2">
                        Operational changes are traceable through immutable audit logs.
                    </li>
                    <li class="rounded-lg bg-white/10 px-3 py-2">
                        Repeated unauthorized attempts are automatically flagged.
                    </li>
                </ul>

                <div
                    class="rounded-lg border border-amber-200/80 bg-amber-100/90 px-4 py-3 text-sm font-semibold text-amber-900">
                    If you reached this page by mistake, do not proceed.
                </div>
            </div>
        </aside>
    </main>
</body>

</html>
