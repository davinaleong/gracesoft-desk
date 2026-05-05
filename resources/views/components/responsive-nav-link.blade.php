@props(['active'])

@php
    $classes =
        $active ?? false
            ? 'block w-full rounded px-3 py-2 text-start text-sm font-semibold text-white bg-violet-500'
            : 'block w-full rounded px-3 py-2 text-start text-sm font-semibold text-violet-50 hover:bg-violet-500/60';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
