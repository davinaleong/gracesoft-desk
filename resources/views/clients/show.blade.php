<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Client Detail') }}
            </h2>

            <a href="{{ route('clients.edit', $client) }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Edit Client') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'client-created')
                <div class="flex items-center justify-between rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <span>{{ __('Client saved. Would you like to create another one?') }}</span>
                    <a href="{{ route('clients.create') }}" class="ml-4 font-semibold underline hover:text-blue-600">{{ __('Create Another') }}</a>
                </div>
            @endif

            @if (session('status') === 'client-updated')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Client updated successfully.') }}
                </div>
            @endif

            @if (in_array(session('status'), ['document-uploaded', 'document-attached', 'document-deleted'], true))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Documents updated.') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Client Code') }}</p>
                        <p class="text-base font-mono">{{ $client->client_code }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Name') }}</p>
                        <p class="text-base font-medium">{{ $client->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Billing Email') }}</p>
                        <p class="text-base">{{ $client->billing_email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Company Reg. / Tax ID') }}</p>
                        <p class="text-base">{{ $client->tax_id ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Currency') }}</p>
                        <p class="text-base">{{ $client->currency }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Default Hourly Rate') }}</p>
                        <p class="text-base">
                            @if ($client->default_hourly_rate !== null)
                                @deskMoney((float) $client->default_hourly_rate)
                            @else
                                {{ __('Not set (uses system default)') }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Status') }}</p>
                        <p class="text-base">{{ ucfirst($client->status) }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs text-gray-500 uppercase">{{ __('Address') }}</p>
                        <p class="text-base whitespace-pre-line">{{ $client->address ?? '—' }}</p>
                    </div>
                    @if ($client->notes)
                        <div class="sm:col-span-2">
                            <p class="text-xs text-gray-500 uppercase">{{ __('Notes') }}</p>
                            <p class="text-base whitespace-pre-line">{{ $client->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Projects --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Projects') }}</h3>
                    @forelse ($client->projects as $project)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                            <a href="{{ route('projects.show', $project) }}" class="text-blue-600 hover:text-blue-800">
                                {{ $project->code }} — {{ $project->name }}
                            </a>
                            <span class="text-gray-500">{{ ucfirst($project->status) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('No projects linked to this client.') }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Transactions --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Recent Transactions') }}</h3>
                    @forelse ($transactions as $transaction)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                            <a href="{{ route('transactions.show', $transaction) }}" class="text-blue-600 hover:text-blue-800 font-mono">
                                {{ $transaction->transaction_code }}
                            </a>
                            <span>@deskDate($transaction->transaction_date)</span>
                            <span class="{{ $transaction->direction === 'in' ? 'text-green-700' : 'text-red-700' }}">
                                @deskMoney((float) $transaction->amount)
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('No transactions linked to this client.') }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Documents --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-700">{{ __('Documents') }}</h3>
                        <div class="flex items-center gap-2">
                            @if ($unlinkedDocuments->isNotEmpty())
                                <button type="button"
                                    onclick="document.getElementById('client-link-panel').classList.toggle('hidden')"
                                    class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                    {{ __('Link Existing') }}
                                </button>
                            @endif
                            <button type="button"
                                onclick="document.getElementById('client-upload-panel').classList.toggle('hidden')"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                {{ __('Attach Document') }}
                            </button>
                        </div>
                    </div>

                    <x-document-link-panel panel-id="client-link-panel" documentable-type="client" :documentable-uuid="$client->uuid"
                        redirect-back="client" :documents="$unlinkedDocuments" />

                    <div id="client-upload-panel"
                        class="{{ $errors->has('file') || $errors->has('name') ? '' : 'hidden' }} mb-6 border border-gray-200 rounded-md p-4 bg-gray-50">
                        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input type="hidden" name="documentable_type" value="client">
                            <input type="hidden" name="documentable_uuid" value="{{ $client->uuid }}">
                            <input type="hidden" name="redirect_back" value="client">

                            <div>
                                <x-input-label for="client-file" :value="__('File')" />
                                <input id="client-file" name="file" type="file" required
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp,.gif"
                                    class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md cursor-pointer focus:outline-none focus:ring focus:ring-indigo-300">
                                <x-input-error :messages="$errors->get('file')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="client-doc-name" :value="__('Display Name (optional)')" />
                                <x-text-input id="client-doc-name" name="name" type="text" class="mt-1 block w-full"
                                    :value="old('name')" placeholder="{{ __('Leave blank to use original filename') }}" />
                            </div>

                            <div class="flex justify-end">
                                <x-primary-button>{{ __('Upload') }}</x-primary-button>
                            </div>
                        </form>
                    </div>

                    @forelse ($client->documents as $document)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div>
                                <span class="text-sm font-medium">{{ $document->name }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ $document->formattedSize() }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <a href="{{ route('documents.preview', $document) }}" target="_blank" rel="noopener noreferrer"
                                    class="text-blue-600 hover:text-blue-800">{{ __('Preview') }}</a>
                                <a href="{{ route('documents.download', $document) }}"
                                    class="text-indigo-600 hover:text-indigo-800">{{ __('Download') }}</a>
                                <form method="POST" action="{{ route('documents.destroy', $document) }}" class="inline"
                                    onsubmit="return confirm('{{ __('Delete this document? This cannot be undone.') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_back" value="client">
                                    <button type="submit" class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
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
