<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Two-Factor Authentication') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Protect your account with TOTP-based two-factor authentication. Scan the QR code, then confirm with a generated code.') }}
        </p>
    </header>

    @if (session('status') === 'two-factor-required')
        <p class="text-sm text-red-600">
            {{ __('Two-factor setup is required before accessing the dashboard.') }}
        </p>
    @endif

    @if (!auth()->user()->two_factor_secret)
        <form method="post" action="/user/two-factor-authentication">
            @csrf

            <x-primary-button>
                {{ __('Enable Two-Factor Authentication') }}
            </x-primary-button>
        </form>
    @else
        <div class="space-y-3">
            <p class="text-sm text-gray-600">
                {{ __('Step 1: Open your authenticator app and scan this QR endpoint:') }}
            </p>
            <a href="/user/two-factor-qr-code" target="_blank" class="text-sm text-blue-600 underline">
                /user/two-factor-qr-code
            </a>

            <p class="text-sm text-gray-600">
                {{ __('Step 2: Enter the current TOTP code to confirm setup.') }}
            </p>

            @if (!auth()->user()->two_factor_confirmed_at)
                <form method="post" action="/user/confirmed-two-factor-authentication" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="code" :value="__('Authentication Code')" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" required
                            autocomplete="one-time-code" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>

                    <x-primary-button>
                        {{ __('Confirm Two-Factor Authentication') }}
                    </x-primary-button>
                </form>
            @else
                <p class="text-sm text-green-600">
                    {{ __('Two-factor authentication is confirmed.') }}
                </p>
            @endif

            <div class="flex gap-3">
                <form method="post" action="/user/two-factor-recovery-codes">
                    @csrf
                    <x-secondary-button>
                        {{ __('Regenerate Recovery Codes') }}
                    </x-secondary-button>
                </form>

                <a href="/user/two-factor-recovery-codes" target="_blank"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    {{ __('View Recovery Codes') }}
                </a>
            </div>

            <form method="post" action="/user/two-factor-authentication">
                @csrf
                @method('delete')

                <x-danger-button>
                    {{ __('Disable Two-Factor Authentication') }}
                </x-danger-button>
            </form>
        </div>
    @endif
</section>
