@props(['title'])

<div {{ $attributes->merge(['class' => 'desk-card']) }}>
    <div class="desk-card-body">
        <h3 class="desk-card-title">{{ $title }}</h3>

        {{ $slot }}
    </div>
</div>
