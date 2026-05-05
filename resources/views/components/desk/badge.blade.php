@props([
    'tone' => 'neutral',
])

@php
    $toneClass = match ($tone) {
        'info' => 'desk-badge-info',
        'success' => 'desk-badge-success',
        default => 'desk-badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => 'desk-badge ' . $toneClass]) }}>
    {{ $slot }}
</span>
