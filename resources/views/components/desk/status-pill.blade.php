@props([
    'status' => 'pending',
])

@php
    $statusClass = match ($status) {
        'in', 'income', 'completed', 'active' => 'desk-status-chip-in',
        'out', 'expense', 'failed', 'inactive' => 'desk-status-chip-out',
        default => 'desk-status-chip-pending',
    };
@endphp

<span {{ $attributes->merge(['class' => 'desk-status-chip ' . $statusClass]) }}>
    {{ strtoupper($status) }}
</span>
