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
                {{ __('Step 1: Open your authenticator app and scan this QR code:') }}
            </p>

            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                {!! auth()->user()->twoFactorQrCodeSvg() !!}
            </div>

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
            </div>

            @php
                $recoveryCodes = auth()->user()->two_factor_recovery_codes ? auth()->user()->recoveryCodes() : [];
            @endphp

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <h3 class="text-sm font-semibold text-gray-900">
                    {{ __('Recovery Codes') }}
                </h3>
                <p class="mt-1 text-xs text-gray-600">
                    {{ __('Store these in a secure place. Each code can be used once.') }}
                </p>

                @if (count($recoveryCodes) > 0)
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($recoveryCodes as $recoveryCode)
                            <li
                                class="rounded border border-gray-200 bg-white px-3 py-2 font-mono text-xs text-gray-800">
                                {{ $recoveryCode }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-xs text-gray-600">
                        {{ __('Recovery codes will appear here after two-factor authentication is enabled.') }}
                    </p>
                @endif
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
