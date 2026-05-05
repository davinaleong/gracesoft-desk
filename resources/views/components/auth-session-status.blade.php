@props(['status'])

@if ($status)
    <div
        {{ $attributes->merge(['class' => 'rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700']) }}>
        {{ $status }}
    </div>
@endif
