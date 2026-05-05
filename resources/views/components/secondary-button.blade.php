<button
    {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-lg border border-[#d9deea] bg-white px-3 py-2 text-sm font-semibold text-[#111322] transition duration-150 hover:bg-violet-50 hover:border-violet-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70']) }}>
    {{ $slot }}
</button>
