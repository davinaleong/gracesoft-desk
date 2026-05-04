@props(['active'])

@php
    $classes =
        $active ?? false
            ? 'inline-flex items-center rounded-md px-2.5 py-1.5 border border-slate-200 bg-slate-50 text-sm font-semibold leading-5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-150 ease-in-out'
            : 'inline-flex items-center rounded-md px-2.5 py-1.5 border border-transparent text-sm font-medium leading-5 text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
