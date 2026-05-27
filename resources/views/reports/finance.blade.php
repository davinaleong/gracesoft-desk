<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <img src="{{ asset('wm.svg') }}" alt="GraceSoft Desk" class="h-3.5 w-auto">
                <div class="flex items-center gap-3">
                    <h2 class="desk-page-title">{{ __('Finance Report') }}</h2>
                    <span class="desk-context-chip">{{ __('Finance') }}</span>
                </div>
            </div>
            <div class="desk-toolbar-actions">
                <a href="{{ route('reports.finance.export', request()->only(['from', 'to'])) }}"
                    class="desk-action-secondary">
                    {{ __('Export CSV') }}
                </a>
                <a href="{{ route('reports.finance.print', request()->only(['from', 'to'])) }}" target="_blank"
                    class="desk-action-primary">
                    {{ __('Print') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="desk-page-shell">
        <div class="desk-page-container desk-stack">
            <div class="desk-card-muted p-6">
                <form method="GET" action="{{ route('reports.finance') }}" class="desk-filter-grid">
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

            <div class="desk-kpi-grid !mb-0 md:grid-cols-4 xl:grid-cols-4">
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Income') }}</p>
                    <p class="desk-kpi-value-positive">@deskMoney((float) $report['totals']['income'])
                    </p>
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Expense') }}</p>
                    <p class="desk-kpi-value-negative">@deskMoney((float) $report['totals']['expense'])
                    </p>
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Pending') }}</p>
                    <p class="desk-kpi-value-pending">@deskMoney((float) $report['totals']['pending'])
                    </p>
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Net') }}</p>
                    <p
                        class="{{ (float) $report['totals']['net'] >= 0 ? 'desk-kpi-value-positive' : 'desk-kpi-value-negative' }}">
                        @deskMoney((float) $report['totals']['net'])
                    </p>
                </div>
            </div>

            <div class="desk-report-grid">
                <div class="desk-card desk-card-body">
                    <h3 class="desk-card-title">{{ __('Expense Breakdown') }}</h3>
                    @php
                        $expenseRows = collect($report['expense_by_category'] ?? [])->filter(
                            fn($row) => is_array($row) || is_object($row),
                        );
                    @endphp
                    <ul class="space-y-2">
                        @forelse ($expenseRows as $row)
                            <li
                                class="flex justify-between rounded-md px-2 py-1 text-sm transition-colors duration-150 hover:bg-slate-100">
                                <span>{{ data_get($row, 'category_name', __('Uncategorized')) }}</span>
                                <span class="font-medium">@deskMoney((float) data_get($row, 'total_amount', 0))</span>
                            </li>
                        @empty
                            <li class="desk-empty-panel">{{ __('No expense data in selected range.') }}</li>
                        @endforelse
                    </ul>
                </div>

                <div class="desk-card desk-card-body">
                    <h3 class="desk-card-title">{{ __('Income Breakdown') }}</h3>
                    @php
                        $incomeRows = collect($report['income_by_category'] ?? [])->filter(
                            fn($row) => is_array($row) || is_object($row),
                        );
                    @endphp
                    <ul class="space-y-2">
                        @forelse ($incomeRows as $row)
                            <li
                                class="flex justify-between rounded-md px-2 py-1 text-sm transition-colors duration-150 hover:bg-slate-100">
                                <span>{{ data_get($row, 'category_name', __('Uncategorized')) }}</span>
                                <span class="font-medium">@deskMoney((float) data_get($row, 'total_amount', 0))</span>
                            </li>
                        @empty
                            <li class="desk-empty-panel">{{ __('No income data in selected range.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
