<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $type === 'vendor' ? __('New Vendor Category') : __('New Service Category') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('settings.categories.store') }}" x-data="{ submitting: false }"
                        @submit="submitting = true" class="space-y-4">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">

                        <div>
                            <x-input-label for="name" :value="__('Category Name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach (['active', 'inactive'] as $s)
                                    <option value="{{ $s }}" @selected(old('status', 'active') === $s)>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-3 mt-6">
                            <x-primary-button x-bind:disabled="submitting">
                                <span x-show="!submitting">{{ __('Save Category') }}</span>
                                <span x-show="submitting">{{ __('Saving...') }}</span>
                            </x-primary-button>
                            <a href="{{ route('settings.categories.index') }}"
                                class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
