<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Documents') }}
            </h2>

            <button type="button" onclick="document.getElementById('upload-panel').classList.toggle('hidden')"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Upload Document') }}
            </button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Upload panel --}}
            <div id="upload-panel"
                class="{{ $errors->has('file') || $errors->has('name') ? '' : 'hidden' }} bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Upload a Document') }}</h3>
                    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data"
                        class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="file" :value="__('File')" />
                            <input id="file" name="file" type="file" required
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp,.gif"
                                class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md cursor-pointer focus:outline-none focus:ring focus:ring-indigo-300">
                            <x-input-error :messages="$errors->get('file')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="name" :value="__('Display Name (optional)')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                :value="old('name')" placeholder="{{ __('Leave blank to use original filename') }}" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>{{ __('Upload') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Document list --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <caption class="sr-only">{{ __('Documents list') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Name') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Linked To') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Size') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Uploaded') }}</th>
                                    <th scope="col" class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($documents as $document)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium">
                                            {{ $document->name }}
                                            <span
                                                class="ml-1 text-xs text-gray-400 uppercase">{{ pathinfo($document->name, PATHINFO_EXTENSION) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            @if ($document->documentable instanceof \App\Models\Transaction)
                                                <a href="{{ route('transactions.show', $document->documentable) }}"
                                                    class="text-blue-600 hover:text-blue-800">
                                                    {{ $document->documentable->transaction_code }}
                                                </a>
                                            @elseif ($document->documentable instanceof \App\Models\Project)
                                                <a href="{{ route('projects.show', $document->documentable) }}"
                                                    class="text-blue-600 hover:text-blue-800">
                                                    {{ $document->documentable->code }} —
                                                    {{ $document->documentable->name }}
                                                </a>
                                            @elseif ($document->documentable instanceof \App\Models\TimeEntry)
                                                <a href="{{ route('time-entries.show', $document->documentable) }}"
                                                    class="text-blue-600 hover:text-blue-800">
                                                    {{ __('Time Entry') }}
                                                    {{ $document->documentable->entry_date->format('d M Y') }}
                                                </a>
                                            @else
                                                &mdash;
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $document->formattedSize() }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            {{ $document->created_at->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-right text-sm space-x-3">
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
                                                <button type="submit"
                                                    class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-sm text-gray-500" colspan="5">
                                            {{ __('No documents uploaded yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $documents->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
