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
                        <p class="text-base">@deskDate($transaction->transaction_date)</p>
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
                            @deskMoney((float) $transaction->amount) /
                            @deskMoney((float) $transaction->gst_amount) /
                            @deskMoney((float) $transaction->net_amount)
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

            {{-- Documents --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-700">{{ __('Documents') }}</h3>
                        <button type="button"
                            onclick="document.getElementById('txn-upload-panel').classList.toggle('hidden')"
                            class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                            {{ __('Attach Document') }}
                        </button>
                    </div>

                    <div id="txn-upload-panel"
                        class="{{ $errors->has('file') || $errors->has('name') ? '' : 'hidden' }} mb-6 border border-gray-200 rounded-md p-4 bg-gray-50">
                        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data"
                            class="space-y-3">
                            @csrf
                            <input type="hidden" name="documentable_type" value="transaction">
                            <input type="hidden" name="documentable_uuid" value="{{ $transaction->uuid }}">
                            <input type="hidden" name="redirect_back" value="transaction">

                            <div>
                                <x-input-label for="txn-file" :value="__('File')" />
                                <input id="txn-file" name="file" type="file" required
                                    class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md cursor-pointer focus:outline-none focus:ring focus:ring-indigo-300">
                                <x-input-error :messages="$errors->get('file')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="txn-doc-name" :value="__('Display Name (optional)')" />
                                <x-text-input id="txn-doc-name" name="name" type="text" class="mt-1 block w-full"
                                    :value="old('name')" placeholder="{{ __('Leave blank to use original filename') }}" />
                            </div>

                            <div class="flex justify-end">
                                <x-primary-button>{{ __('Upload') }}</x-primary-button>
                            </div>
                        </form>
                    </div>

                    @forelse ($transaction->documents as $document)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div>
                                <span class="text-sm font-medium">{{ $document->name }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ $document->formattedSize() }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <a href="{{ route('documents.preview', $document) }}" target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-blue-600 hover:text-blue-800">{{ __('Preview') }}</a>
                                <a href="{{ route('documents.download', $document) }}"
                                    class="text-indigo-600 hover:text-indigo-800">{{ __('Download') }}</a>
                                <form method="POST" action="{{ route('documents.destroy', $document) }}"
                                    class="inline"
                                    onsubmit="return confirm('{{ __('Delete this document? This cannot be undone.') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_back" value="transaction">
                                    <button type="submit"
                                        class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('No documents attached.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
