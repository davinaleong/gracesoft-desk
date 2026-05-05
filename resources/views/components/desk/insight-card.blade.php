@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'desk-card-muted']) }}>
    <div class="desk-card-body">
        <h3 class="desk-card-title">{{ $title }}</h3>

        @if ($description)
            <p class="text-sm text-slate-600">{{ $description }}</p>
        @endif

        {{ $slot }}
    </div>
</div>
