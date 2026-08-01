<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Time Entry Details') }}
            </h2>

            <div class="flex items-center gap-3">
                <a href="{{ route('time-entries.edit', $timeEntry) }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Edit') }}
                </a>

                <form method="POST" action="{{ route('time-entries.destroy', $timeEntry) }}"
                    onsubmit="return confirm('{{ __('Delete this time entry?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'time-entry-created')
                <div
                    class="flex items-center justify-between rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <span>{{ __('Time entry saved. Would you like to add another one?') }}</span>
                    <a href="{{ route('time-entries.create') }}"
                        class="ml-4 font-semibold underline hover:text-blue-600">{{ __('Add Another') }}</a>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Project') }}</dt>
                            <dd class="text-base">{{ $timeEntry->project?->code }} - {{ $timeEntry->project?->name }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Stage') }}</dt>
                            <dd class="text-base">{{ $timeEntry->stage?->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Entry Date') }}</dt>
                            <dd class="text-base">@deskDate($timeEntry->entry_date)</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Duration') }}</dt>
                            <dd class="text-base">@deskDuration($timeEntry->duration_minutes)</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Hourly Rate') }}</dt>
                            <dd class="text-base">@deskMoney((float) ($timeEntry->project?->hourly_rate ?? 0))</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Billable Amount') }}</dt>
                            <dd class="text-base">@deskMoney((float) $timeEntry->billable_amount)</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-gray-500">{{ __('Notes') }}</dt>
                            <dd class="text-base whitespace-pre-line">{{ $timeEntry->notes ?: 'N/A' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6">
                        <a href="{{ route('time-entries.index') }}"
                            class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to Time Entries') }}</a>
                    </div>
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
                                    onclick="document.getElementById('te-link-panel').classList.toggle('hidden')"
                                    class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                    {{ __('Link Existing') }}
                                </button>
                            @endif
                            <button type="button"
                                onclick="document.getElementById('te-upload-panel').classList.toggle('hidden')"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                {{ __('Attach Document') }}
                            </button>
                        </div>
                    </div>

                    <x-document-link-panel panel-id="te-link-panel" documentable-type="time-entry" :documentable-uuid="$timeEntry->uuid"
                        redirect-back="time-entry" :documents="$unlinkedDocuments" />

                    <div id="te-upload-panel"
                        class="{{ $errors->has('file') || $errors->has('name') ? '' : 'hidden' }} mb-6 border border-gray-200 rounded-md p-4 bg-gray-50">
                        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data"
                            class="space-y-3">
                            @csrf
                            <input type="hidden" name="documentable_type" value="time-entry">
                            <input type="hidden" name="documentable_uuid" value="{{ $timeEntry->uuid }}">
                            <input type="hidden" name="redirect_back" value="time-entry">

                            <div>
                                <x-input-label for="te-file" :value="__('File')" />
                                <input id="te-file" name="file" type="file" required
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp,.gif"
                                    class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md cursor-pointer focus:outline-none focus:ring focus:ring-indigo-300">
                                <x-input-error :messages="$errors->get('file')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="te-doc-name" :value="__('Display Name (optional)')" />
                                <x-text-input id="te-doc-name" name="name" type="text" class="mt-1 block w-full"
                                    :value="old('name')" placeholder="{{ __('Leave blank to use original filename') }}" />
                            </div>

                            <div class="flex justify-end">
                                <x-primary-button>{{ __('Upload') }}</x-primary-button>
                            </div>
                        </form>
                    </div>

                    @forelse ($timeEntry->documents as $document)
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
                                <a href="{{ route('documents.edit', $document) }}"
                                    class="text-gray-600 hover:text-gray-900">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('documents.destroy', $document) }}"
                                    class="inline"
                                    onsubmit="return confirm('{{ __('Delete this document? This cannot be undone.') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_back" value="time-entry">
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
