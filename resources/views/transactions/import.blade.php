<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Import Transactions CSV') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600 mb-4">
                    {{ __('Upload a CSV file with account_code, type, direction, status, transaction_date, amount, and gst_amount. Existing rows map by transaction_code where provided; incoming id and uuid columns are ignored.') }}
                </p>

                <form method="POST" action="{{ route('transactions.import.preview') }}" enctype="multipart/form-data"
                    class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="csv_file" :value="__('CSV File')" />
                        <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        <x-input-error :messages="$errors->get('csv_file')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>{{ __('Preview Import') }}</x-primary-button>
                        <a href="{{ route('transactions.index') }}"
                            class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
