@props(['disabled' => false])

<input @disabled($disabled)
    {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-[#d9deea] bg-[#fbfbfe] px-3 py-2 text-sm text-[#111322] shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 focus-visible:ring-offset-1']) }}>
