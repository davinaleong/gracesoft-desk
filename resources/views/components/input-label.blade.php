@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-[#1f2438]']) }}>
    {{ $value ?? $slot }}
</label>
