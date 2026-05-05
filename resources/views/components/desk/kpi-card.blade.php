@props(['label', 'value', 'tone' => 'default'])

@php
    $valueClass = match ($tone) {
        'positive' => 'desk-kpi-value-positive',
        'negative' => 'desk-kpi-value-negative',
        'pending' => 'desk-kpi-value-pending',
        default => 'desk-kpi-value',
    };
@endphp

<div class="desk-kpi-card">
    <p class="desk-kpi-label">{{ $label }}</p>
    <p class="{{ $valueClass }}">{{ $value }}</p>
</div>
