<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description"
        content="GraceSoft Desk internal operations cockpit for finance, projects, time entries, and reporting.">
    <meta name="theme-color" content="#0f172a">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    @php
        $status = session('status');
        $flashMessage = match ($status) {
            'project-created' => __('Project created successfully.'),
            'project-updated' => __('Project updated successfully.'),
            'time-entry-created' => __('Time entry created successfully.'),
            'time-entry-updated' => __('Time entry updated successfully.'),
            'time-entry-deleted' => __('Time entry deleted successfully.'),
            'transaction-created' => __('Transaction created successfully.'),
            'transaction-updated' => __('Transaction updated successfully.'),
            'system-settings-updated' => __('System settings updated successfully.'),
            'password-updated' => __('Password updated successfully.'),
            'two-factor-authentication-enabled' => __('Two-factor authentication enabled.'),
            'two-factor-authentication-confirmed' => __('Two-factor authentication confirmed.'),
            'two-factor-authentication-disabled' => __('Two-factor authentication disabled.'),
            'archive-mode-read-only' => __(
                'Archive mode is enabled. This action is blocked while the desk is read-only.',
            ),
            default => is_string($status) ? $status : null,
        };
    @endphp

    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:bg-white focus:px-3 focus:py-2 focus:rounded-md focus:shadow">
        {{ __('Skip to main content') }}
    </a>

    <div class="min-h-screen bg-slate-50">
        @include('layouts.navigation')

        @if ($flashMessage)
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                <div role="status" aria-live="polite"
                    class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ $flashMessage }}
                </div>
            </div>
        @endif

        <!-- Page Heading -->
        @isset($header)
            <header class="border-b border-slate-200 bg-white/90">
                <div class="max-w-7xl mx-auto px-4 py-5 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main id="main-content" tabindex="-1">
            {{ $slot }}
        </main>
    </div>
</body>

</html>
