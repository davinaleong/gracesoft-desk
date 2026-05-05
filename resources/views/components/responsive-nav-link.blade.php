@props(['active'])

@php
    $classes =
        $active ?? false
            ? 'block w-full rounded px-3 py-2 text-start text-sm font-semibold no-underline text-white bg-indigo-500'
            : 'block w-full rounded px-3 py-2 text-start text-sm font-semibold no-underline text-indigo-50 hover:bg-indigo-500/60';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
