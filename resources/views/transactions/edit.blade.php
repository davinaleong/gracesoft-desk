<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Transaction') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('transactions.update', $transaction) }}" x-data="{ submitting: false }"
                        @submit="submitting = true">
                        @method('PUT')
                        @php($submitLabel = __('Save Changes'))
                        @include('transactions._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
