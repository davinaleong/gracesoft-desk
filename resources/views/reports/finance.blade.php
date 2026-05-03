<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Finance Report') }}</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.finance.export', request()->only(['from', 'to'])) }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Export CSV') }}
                </a>
                <a href="{{ route('reports.finance.print', request()->only(['from', 'to'])) }}" target="_blank"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Print') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('reports.finance') }}"
                    class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="from" :value="__('From')" />
                        <x-text-input id="from" name="from" type="date" class="mt-1 block w-full"
                            :value="$report['range']['from']" />
                    </div>
                    <div>
                        <x-input-label for="to" :value="__('To')" />
                        <x-text-input id="to" name="to" type="date" class="mt-1 block w-full"
                            :value="$report['range']['to']" />
                    </div>
                    <div class="sm:self-end">
                        <x-primary-button>{{ __('Apply Filter') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Income') }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ number_format((float) $report['totals']['income'], 2) }}
                    </p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Expense') }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ number_format((float) $report['totals']['expense'], 2) }}
                    </p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Pending') }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ number_format((float) $report['totals']['pending'], 2) }}
                    </p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Net') }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ number_format((float) $report['totals']['net'], 2) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Expense Breakdown') }}</h3>
                    <ul class="space-y-2">
                        @forelse ($report['expense_by_category'] as $row)
                            <li class="flex justify-between text-sm">
                                <span>{{ $row->category_name }}</span>
                                <span class="font-medium">{{ number_format((float) $row->total_amount, 2) }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">{{ __('No expense data in selected range.') }}</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Income Breakdown') }}</h3>
                    <ul class="space-y-2">
                        @forelse ($report['income_by_category'] as $row)
                            <li class="flex justify-between text-sm">
                                <span>{{ $row->category_name }}</span>
                                <span class="font-medium">{{ number_format((float) $row->total_amount, 2) }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">{{ __('No income data in selected range.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
