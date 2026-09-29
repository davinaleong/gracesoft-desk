<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Draft Invoice') }} — {{ $invoice->client->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('invoices.update', $invoice) }}" x-data="{ submitting: false }" @submit="submitting = true"
                class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @csrf
                @method('PUT')

                <div class="p-6 text-gray-900 space-y-6">
                    @if ($errors->has('invoice'))
                        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('invoice') }}</div>
                    @endif

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('Time & Milestone Lines') }}</h3>
                        @forelse ($invoice->lines->whereIn('type', ['time', 'milestone']) as $line)
                            <label class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 text-sm">
                                <span>{{ $line->description }} <span class="text-gray-500">({{ $line->quantity }} h, {{ number_format((float) $line->amount, 2) }})</span></span>
                                <span class="flex items-center gap-1 text-red-600">
                                    <input type="checkbox" name="remove_line_uuids[]" value="{{ $line->uuid }}"
                                        class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    {{ __('Remove') }}
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('No time lines.') }}</p>
                        @endforelse
                    </div>

                    @include('invoices._manual-lines', [
                        'initialLines' => old('manual_lines', $invoice->lines->where('type', 'manual')->map(fn ($line) => [
                            'description' => $line->description,
                            'quantity' => $line->quantity,
                            'unit_price' => $line->unit_price,
                        ])->values()->all()),
                    ])

                    <div>
                        <x-input-label for="notes" :value="__('Notes (printed on the invoice)')" />
                        <textarea id="notes" name="notes" rows="3"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $invoice->notes) }}</textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button x-bind:disabled="submitting">{{ __('Save Draft') }}</x-primary-button>
                        <a href="{{ route('invoices.show', $invoice) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
