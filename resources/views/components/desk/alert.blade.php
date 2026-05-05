@props([
    'tone' => 'info',
])

@php
    $toneClass = match ($tone) {
        'success' => 'desk-alert-success',
        'warning' => 'desk-alert-warning',
        'danger' => 'desk-alert-danger',
        default => 'desk-alert-info',
    };
@endphp

<div {{ $attributes->merge(['class' => 'desk-alert ' . $toneClass, 'role' => 'status']) }}>
    {{ $slot }}
</div>
