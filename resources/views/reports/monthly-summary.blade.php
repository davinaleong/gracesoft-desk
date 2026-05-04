<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="desk-brand-kicker">{{ __('GraceSoft Desk') }}</p>
                <div class="flex items-center gap-3">
                    <h2 class="desk-page-title">{{ __('Monthly Summary') }}</h2>
                    <span class="desk-context-chip">{{ __('Ledger') }}</span>
                </div>
            </div>
            <div class="desk-toolbar-actions">
                <a href="{{ route('reports.monthly-summary.export', request()->only(['from', 'to'])) }}"
                    class="desk-action-secondary">
                    {{ __('Export CSV') }}
                </a>
                <a href="{{ route('reports.monthly-summary.print', request()->only(['from', 'to'])) }}" target="_blank"
                    class="desk-action-primary">
                    {{ __('Print') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="desk-page-shell">
        <div class="desk-page-container desk-stack">
            <div class="desk-card-muted p-6">
                <form method="GET" action="{{ route('reports.monthly-summary') }}" class="desk-filter-grid">
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

            <div class="desk-card desk-card-body">
                <h3 class="desk-card-title">{{ __('Monthly Ledger') }}</h3>
                <div class="overflow-x-auto">
                    <table class="desk-table-dense min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="desk-table-head">
                                    {{ __('Month') }}</th>
                                <th class="desk-table-head">
                                    {{ __('Income') }}</th>
                                <th class="desk-table-head">
                                    {{ __('Expense') }}</th>
                                <th class="desk-table-head">
                                    {{ __('Pending') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($report['monthly_summary'] as $row)
                                <tr class="desk-interactive-row">
                                    <td class="desk-table-cell">{{ $row['month_label'] }}</td>
                                    <td class="desk-table-cell">@deskMoney((float) $row['income_total'])</td>
                                    <td class="desk-table-cell">@deskMoney((float) $row['expense_total'])</td>
                                    <td class="desk-table-cell">@deskMoney((float) $row['pending_total'])</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="desk-table-empty" colspan="4">
                                        <div class="desk-empty-panel">
                                            {{ __('No ledger data in selected range.') }}
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="desk-card desk-card-body">
                <h3 class="desk-card-title">{{ __('Account Summary') }}</h3>
                <div class="overflow-x-auto">
                    <table class="desk-table-dense min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="desk-table-head">
                                    {{ __('Account') }}</th>
                                <th class="desk-table-head">
                                    {{ __('Income') }}</th>
                                <th class="desk-table-head">
                                    {{ __('Expense') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($report['account_summary'] as $row)
                                <tr class="desk-interactive-row">
                                    <td class="desk-table-cell"><span class="font-mono">{{ $row->code }}</span> -
                                        {{ $row->name }}</td>
                                    <td class="desk-table-cell">@deskMoney((float) $row->income_total)</td>
                                    <td class="desk-table-cell">@deskMoney((float) $row->expense_total)</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="desk-table-empty" colspan="3">
                                        <div class="desk-empty-panel">
                                            {{ __('No account summary data in selected range.') }}
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
