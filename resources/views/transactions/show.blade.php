<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Transaction Detail') }}
            </h2>

            <a href="{{ route('transactions.edit', $transaction) }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Edit Transaction') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Transaction Code') }}</p>
                        <p class="font-mono text-base">{{ $transaction->transaction_code }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Date') }}</p>
                        <p class="text-base">{{ $transaction->transaction_date?->toDateString() }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Type / Direction / Status') }}</p>
                        <p class="text-base">{{ $transaction->type }} / {{ $transaction->direction }} /
                            {{ $transaction->status }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Account') }}</p>
                        <p class="text-base">{{ $transaction->account?->name }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Amount / GST / Net') }}</p>
                        <p class="text-base">
                            {{ number_format((float) $transaction->amount, 2) }} /
                            {{ number_format((float) $transaction->gst_amount, 2) }} /
                            {{ number_format((float) $transaction->net_amount, 2) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Reference') }}</p>
                        <p class="text-base">{{ $transaction->reference ?: __('N/A') }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Description') }}</p>
                        <p class="text-base">{{ $transaction->description ?: __('No description.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
