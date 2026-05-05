<nav x-data="{ open: false }" class="lg:w-72 lg:shrink-0">
    <div class="flex items-center justify-between bg-violet-700 px-4 py-3 text-white lg:hidden">
        <a href="{{ route('dashboard') }}">
            <img src="{{ asset('wm-w.svg') }}" alt="GraceSoft Desk" class="h-8 w-auto">
        </a>

        <button @click="open = ! open" class="rounded-md border border-violet-400/70 px-2 py-1 text-sm font-semibold">
            {{ __('Menu') }}
        </button>
    </div>

    <aside class="hidden min-h-screen flex-col justify-between bg-violet-700 text-white lg:flex lg:sticky lg:top-0">
        <div class="space-y-4">
            <header class="border-b border-violet-500 p-6">
                <img src="{{ asset('wm-w.svg') }}" alt="GraceSoft Desk" class="mx-auto h-auto w-full max-w-[180px]">
            </header>

            <div class="space-y-2 p-6 pt-2">
                <h2 class="text-xs font-bold uppercase tracking-wide text-violet-300">{{ __('Operations') }}</h2>
                <div class="space-y-1 text-sm">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('Dashboard') }}</x-nav-link>
                    <x-nav-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">{{ __('Projects') }}</x-nav-link>
                    <x-nav-link :href="route('time-entries.index')" :active="request()->routeIs('time-entries.*')">{{ __('Time Entries') }}</x-nav-link>
                    <x-nav-link :href="route('transactions.index')" :active="request()->routeIs('transactions.*')">{{ __('Transactions') }}</x-nav-link>
                    <x-nav-link :href="route('reports.finance')" :active="request()->routeIs('reports.*')">{{ __('Reports') }}</x-nav-link>
                    <x-nav-link :href="route('settings.system.edit')" :active="request()->routeIs('settings.system.*')">{{ __('System Settings') }}</x-nav-link>
                </div>
            </div>
        </div>

        <footer class="space-y-3 border-t border-violet-500 p-6 text-sm">
            <a href="{{ route('profile.edit') }}"
                class="block font-semibold text-violet-50 hover:text-violet-200">{{ __('Profile') }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="block font-semibold text-violet-50 hover:text-violet-200">{{ __('Log Out') }}</button>
            </form>
        </footer>
    </aside>

    <div :class="{ 'block': open, 'hidden': !open }"
        class="hidden border-b border-violet-400 bg-violet-700 px-4 pb-4 pt-2 text-white lg:hidden">
        <div class="space-y-1 text-sm">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('Dashboard') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">{{ __('Projects') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('time-entries.index')"
                :active="request()->routeIs('time-entries.*')">{{ __('Time Entries') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('transactions.index')"
                :active="request()->routeIs('transactions.*')">{{ __('Transactions') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reports.finance')" :active="request()->routeIs('reports.*')">{{ __('Reports') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('settings.system.edit')"
                :active="request()->routeIs('settings.system.*')">{{ __('System Settings') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('profile.edit')">{{ __('Profile') }}</x-responsive-nav-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="block w-full rounded px-3 py-2 text-left text-sm font-semibold text-violet-50 hover:bg-violet-500/60">{{ __('Log Out') }}</button>
            </form>
        </div>
    </div>
</nav>
