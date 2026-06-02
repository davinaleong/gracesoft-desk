<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Document') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('documents.update', $document) }}" x-data="{ submitting: false }"
                        @submit="submitting = true">
                        @csrf
                        @method('PUT')

                        <div class="space-y-4">
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                    :value="old('name', $document->name)" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <div class="flex items-center justify-end gap-4">
                                <a href="{{ route('documents.index') }}"
                                    class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                                <x-primary-button :disabled="$submitting ?? false">
                                    {{ __('Save Changes') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
