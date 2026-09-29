<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Invoices') }}
            </h2>

            <a href="{{ route('invoices.create') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New Invoice') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status') === 'invoice-deleted')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Draft invoice deleted. Its time entries are available to bill again.') }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('invoices.index') }}" class="flex items-center gap-3">
                    <select name="status" aria-label="{{ __('Status') }}"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('All Statuses') }}</option>
                        @foreach (\App\Models\Invoice::STATUSES as $statusOption)
                            <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ ucfirst($statusOption) }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        {{ __('Filter') }}
                    </button>
                </form>

                <p class="text-sm text-gray-700">
                    {{ __('Outstanding receivables:') }}
                    <span class="font-semibold">@deskMoney($outstandingTotal)</span>
                </p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <caption class="sr-only">{{ __('Invoices list') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Number') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Client') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Issued') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Due') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total') }}</th>
                                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($invoices as $invoice)
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm">{{ $invoice->displayNumber() }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $invoice->client?->name }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $invoice->issue_date ? \App\Support\DeskFormat::date($invoice->issue_date) : '—' }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $invoice->due_date ? \App\Support\DeskFormat::date($invoice->due_date) : '—' }}</td>
                                        <td class="px-4 py-3 text-sm">@include('invoices._status', ['status' => $invoice->status])</td>
                                        <td class="px-4 py-3 text-sm text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-800">{{ __('View') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-sm text-gray-500" colspan="7">{{ __('No invoices yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $invoices->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
