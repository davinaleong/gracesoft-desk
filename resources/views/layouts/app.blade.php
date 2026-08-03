<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description"
        content="GraceSoft Desk internal operations cockpit for finance, projects, time entries, and reporting.">
    <meta name="theme-color" content="#0f172a">

    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-[#f7f8fc] text-[#111322]">
    @php
        $status = session('status');
        $flashMessage = match ($status) {
            'project-created' => __('Project saved successfully.'),
            'project-updated' => __('Project details have been updated.'),
            'project-stage-created' => __('Stage added successfully.'),
            'project-stage-updated' => __('Stage updated successfully.'),
            'project-stage-deleted' => __('Stage deleted.'),
            'project-stage-in-use' => __('This stage cannot be deleted because time entries are assigned to it.'),
            'time-entry-created' => __('Time entry added successfully.'),
            'time-entry-updated' => __('Time entry has been updated.'),
            'time-entry-deleted' => __('Time entry has been removed.'),
            'transaction-created' => __('Transaction saved successfully.'),
            'transaction-updated' => __('Transaction details have been updated.'),
            'document-uploaded' => __('Document uploaded successfully.'),
            'document-deleted' => __('Document has been deleted.'),
            'system-settings-updated' => __('System settings have been saved.'),
            'password-updated' => __('Your password has been updated.'),
            'profile-updated' => __('Your profile details have been updated.'),
            'verification-link-sent' => __('We sent a fresh verification link to your email address.'),
            'password-change-required' => __('Please change your temporary password before continuing.'),
            'two-factor-required' => __('Please finish setting up two-factor authentication to continue.'),
            'two-factor-authentication-enabled' => __(
                'Two-factor authentication is now enabled. Please confirm it using your authenticator app.',
            ),
            'two-factor-authentication-confirmed' => __('Two-factor authentication setup is complete.'),
            'two-factor-authentication-disabled' => __('Two-factor authentication has been disabled.'),
            'archive-mode-read-only' => __(
                'Archive mode is enabled. This action is blocked while the desk is read-only.',
            ),
            'github-repo-linked' => __('GitHub repository linked successfully.'),
            'github-repo-unlinked' => __('GitHub repository has been unlinked.'),
            default => is_string($status) ? $status : null,
        };
    @endphp

    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:bg-white focus:px-3 focus:py-2 focus:rounded-md focus:shadow">
        {{ __('Skip to main content') }}
    </a>

    <div class="min-h-screen lg:flex">
        @include('layouts.navigation')

        <div class="min-h-screen flex-1">
            @if ($flashMessage)
                <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
                    <div role="status" aria-live="polite"
                        class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ $flashMessage }}
                    </div>
                </div>
            @endif

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-[#d9deea] bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main id="main-content" tabindex="-1">
                {{ $slot }}
            </main>
        </div>
    </div>
    @stack('scripts')
</body>

</html>
