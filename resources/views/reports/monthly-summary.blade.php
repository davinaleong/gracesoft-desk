<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Monthly Summary') }}</h2>
            <a href="{{ route('reports.monthly-summary.print', request()->only(['from', 'to'])) }}" target="_blank"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Print') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('reports.monthly-summary') }}"
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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Monthly Ledger') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Month') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Income') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Expense') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Pending') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($report['monthly_summary'] as $row)
                                <tr>
                                    <td class="px-3 py-2 text-sm">{{ $row['month_label'] }}</td>
                                    <td class="px-3 py-2 text-sm">{{ number_format((float) $row['income_total'], 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm">{{ number_format((float) $row['expense_total'], 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm">{{ number_format((float) $row['pending_total'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-4 text-sm text-gray-500" colspan="4">
                                        {{ __('No ledger data in selected range.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Account Summary') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Account') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Income') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Expense') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($report['account_summary'] as $row)
                                <tr>
                                    <td class="px-3 py-2 text-sm"><span class="font-mono">{{ $row->code }}</span> -
                                        {{ $row->name }}</td>
                                    <td class="px-3 py-2 text-sm">{{ number_format((float) $row->income_total, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm">{{ number_format((float) $row->expense_total, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-4 text-sm text-gray-500" colspan="3">
                                        {{ __('No account summary data in selected range.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
