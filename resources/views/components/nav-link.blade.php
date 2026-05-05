@props(['active'])

@php
    $classes =
        $active ?? false
            ? 'flex items-center justify-between rounded px-2.5 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:ring-offset-2 focus-visible:ring-offset-violet-600 bg-violet-500 text-white'
            : 'flex items-center justify-between rounded px-2.5 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:ring-offset-2 focus-visible:ring-offset-violet-600 text-violet-50 hover:bg-violet-500/60';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
