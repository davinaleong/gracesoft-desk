<x-guest-layout>
    <article class="space-y-5">
        <header>
            <h1 class="font-serif text-3xl font-bold text-[#111322]">{{ __('Sign in to GraceSoft Desk') }}</h1>
            <p class="mt-1 text-[#5f6477]">{{ __('Use your work credentials to continue to the operations dashboard.') }}
            </p>
        </header>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form class="space-y-4" method="POST" action="{{ route('login') }}" novalidate>
            @csrf

            <!-- Email Address -->
            <div class="space-y-1">
                <x-input-label for="email" :value="__('Email *')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')"
                    placeholder="you@example.com" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div class="space-y-1">
                <x-input-label for="password" :value="__('Password *')" />

                <x-text-input id="password" type="password" name="password" placeholder="********" required
                    autocomplete="current-password" />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Remember Me -->
            <div class="block">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox"
                        class="rounded border-[#d9deea] text-indigo-700 shadow-sm focus:ring-indigo-400"
                        name="remember">
                    <span class="ms-2 text-sm text-[#5f6477]">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="pt-1">
                <x-primary-button class="w-full">
                    {{ __('Sign In') }}
                </x-primary-button>
            </div>
        </form>
    </article>
</x-guest-layout>
