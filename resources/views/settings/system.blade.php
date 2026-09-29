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

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
                                <x-input-label for="locale" :value="__('Locale')" />
                                <x-text-input id="locale" name="locale" type="text" class="mt-1 block w-full"
                                    :value="old('locale', $settings['locale'])" required />
                                <x-input-error :messages="$errors->get('locale')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="default_hourly_rate" :value="__('Default Hourly Rate')" />
                                <x-text-input id="default_hourly_rate" name="default_hourly_rate" type="number"
                                    min="0" step="0.01" class="mt-1 block w-full" :value="old('default_hourly_rate', $settings['default_hourly_rate'])"
                                    required />
                                <x-input-error :messages="$errors->get('default_hourly_rate')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="weekly_hours_target" :value="__('Weekly Hours Target (optional)')" />
                                <x-text-input id="weekly_hours_target" name="weekly_hours_target" type="number" min="0" max="168" step="0.5"
                                    class="mt-1 block w-full" :value="old('weekly_hours_target', $settings['weekly_hours_target'])" />
                                <x-input-error :messages="$errors->get('weekly_hours_target')" class="mt-2" />
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">{{ __('Invoicing') }}</h3>

                            <div>
                                <x-input-label for="company_address" :value="__('Company Address (printed on invoices)')" />
                                <textarea id="company_address" name="company_address" rows="3"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('company_address', $settings['company_address']) }}</textarea>
                                <x-input-error :messages="$errors->get('company_address')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="gst_registration_number" :value="__('GST Registration No.')" />
                                    <x-text-input id="gst_registration_number" name="gst_registration_number" type="text" class="mt-1 block w-full"
                                        :value="old('gst_registration_number', $settings['gst_registration_number'])" />
                                    <p class="mt-1 text-xs text-gray-500">{{ __('Leave blank if not GST-registered; invoices will then carry no GST.') }}</p>
                                    <x-input-error :messages="$errors->get('gst_registration_number')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="gst_rate" :value="__('GST Rate (%)')" />
                                    <x-text-input id="gst_rate" name="gst_rate" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full"
                                        :value="old('gst_rate', $settings['gst_rate'])" required />
                                    <x-input-error :messages="$errors->get('gst_rate')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="payment_terms_days" :value="__('Payment Terms (days)')" />
                                    <x-text-input id="payment_terms_days" name="payment_terms_days" type="number" min="0" max="365" step="1" class="mt-1 block w-full"
                                        :value="old('payment_terms_days', $settings['payment_terms_days'])" required />
                                    <x-input-error :messages="$errors->get('payment_terms_days')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="invoice_footer" :value="__('Invoice Footer')" />
                                <textarea id="invoice_footer" name="invoice_footer" rows="3"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('invoice_footer', $settings['invoice_footer']) }}</textarea>
                                <x-input-error :messages="$errors->get('invoice_footer')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input type="hidden" name="archive_mode" value="0">
                            <input id="archive_mode" name="archive_mode" type="checkbox" value="1"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked(old('archive_mode', $settings['archive_mode']))>
                            <x-input-label for="archive_mode" :value="__('Archive Mode (Read-Only)')" />
                        </div>
                        <p class="text-sm text-gray-600">
                            {{ __('When enabled, create/update/delete actions are blocked across the desk except this settings page.') }}
                        </p>

                        <div class="pt-2">
                            <x-primary-button>{{ __('Save Settings') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
