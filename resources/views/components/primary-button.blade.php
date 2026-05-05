<button
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 font-semibold text-sm transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70 bg-violet-600 text-white hover:bg-violet-700']) }}>
    {{ $slot }}
</button>
