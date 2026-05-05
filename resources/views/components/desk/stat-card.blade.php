@props(['label', 'value'])

<div {{ $attributes->merge(['class' => 'desk-card']) }}>
    <div class="desk-card-body">
        <p class="desk-kpi-label">{{ $label }}</p>
        <p class="desk-kpi-value">{{ $value }}</p>
    </div>
</div>
