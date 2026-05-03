<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('System Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if (session('status') === 'system-settings-updated')
                        <p class="mb-4 text-sm text-green-600">{{ __('System settings updated successfully.') }}</p>
                    @endif

                    <form method="POST" action="{{ route('settings.system.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="company_name" :value="__('Company Name')" />
                            <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full"
                                :value="old('company_name', $settings['company_name'])" required />
                            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="company_email" :value="__('Company Email')" />
                            <x-text-input id="company_email" name="company_email" type="email"
                                class="mt-1 block w-full" :value="old('company_email', $settings['company_email'])" />
                            <x-input-error :messages="$errors->get('company_email')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="default_currency" :value="__('Default Currency')" />
                                <x-text-input id="default_currency" name="default_currency" type="text"
                                    class="mt-1 block w-full" :value="old('default_currency', $settings['default_currency'])" required />
                                <x-input-error :messages="$errors->get('default_currency')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="timezone" :value="__('Timezone')" />
                                <x-text-input id="timezone" name="timezone" type="text" class="mt-1 block w-full"
                                    :value="old('timezone', $settings['timezone'])" required />
                                <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="default_hourly_rate" :value="__('Default Hourly Rate')" />
                                <x-text-input id="default_hourly_rate" name="default_hourly_rate" type="number"
                                    min="0" step="0.01" class="mt-1 block w-full" :value="old('default_hourly_rate', $settings['default_hourly_rate'])"
                                    required />
                                <x-input-error :messages="$errors->get('default_hourly_rate')" class="mt-2" />
                            </div>
                        </div>

                        <div class="pt-2">
                            <x-primary-button>{{ __('Save Settings') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
