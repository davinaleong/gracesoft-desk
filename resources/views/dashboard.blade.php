<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="desk-brand-kicker">{{ __('GraceSoft Desk') }}</p>
                <h2 class="desk-page-title">
                    {{ __('Dashboard') }}
                </h2>
            </div>

            <span class="desk-context-chip">
                {{ now()->format('F Y') }}
            </span>
        </div>
    </x-slot>

    <div class="desk-page-shell">
        <div class="desk-page-container">
            <div class="desk-kpi-grid">
                <x-desk.kpi-card :label="__('Active Projects')" :value="$dashboard['kpis']['active_projects']" />
                <x-desk.kpi-card :label="__('Total Logged Hours')" :value="number_format((float) $dashboard['kpis']['total_logged_hours'], 2)" />
                <x-desk.kpi-card :label="__('Total Billable Value')" :value="\App\Support\DeskFormat::money((float) $dashboard['kpis']['total_billable_value'])" />
                <x-desk.kpi-card :label="__('Net Cashflow (This Month)')" :value="\App\Support\DeskFormat::money((float) $dashboard['kpis']['net_cashflow_this_month'])" :tone="(float) $dashboard['kpis']['net_cashflow_this_month'] >= 0 ? 'positive' : 'negative'" />
            </div>

            <div class="desk-content-grid">
                <x-desk.chart-card class="xl:col-span-2" :title="__('Monthly Net Cashflow')">
                    <div id="monthly-cashflow-chart" class="h-80"></div>
                </x-desk.chart-card>

                <x-desk.chart-card :title="__('Expense Breakdown')">
                    <div id="expense-breakdown-chart" class="h-80"></div>
                </x-desk.chart-card>

                <x-desk.chart-card :title="__('Income Breakdown')">
                    <div id="income-breakdown-chart" class="h-80"></div>
                </x-desk.chart-card>

                <x-desk.chart-card class="xl:col-span-2" :title="__('Billable by Project')">
                    <div id="billable-by-project-chart" class="h-80"></div>
                </x-desk.chart-card>

                <x-desk.chart-card class="xl:col-span-2" :title="__('Billable by Stage')">
                    <div id="billable-by-stage-chart" class="h-80"></div>
                </x-desk.chart-card>

                <x-desk.table-card class="xl:col-span-2" :title="__('Pending / Outstanding Transactions')">

                    <div class="overflow-x-auto">
                        <table class="desk-table-dense min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="desk-table-head">
                                        {{ __('Ref') }}</th>
                                    <th class="desk-table-head">
                                        {{ __('Date') }}</th>
                                    <th class="desk-table-head">
                                        {{ __('Direction') }}</th>
                                    <th class="desk-table-head">
                                        {{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($dashboard['pending_transactions'] as $row)
                                    <tr class="desk-interactive-row">
                                        <td class="desk-table-cell font-mono">{{ $row['code'] }}</td>
                                        <td class="desk-table-cell">@deskDate($row['date'])</td>
                                        <td class="desk-table-cell">
                                            <x-desk.status-pill :status="$row['direction']" />
                                        </td>
                                        <td class="desk-table-cell">
                                            @deskMoney((float) $row['net_amount'])</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="desk-table-empty" colspan="4">
                                            <div class="desk-empty-panel">
                                                {{ __('No pending transactions in the selected reporting period.') }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-desk.table-card>

                <x-desk.table-card class="xl:col-span-2" :title="__('Project Overview')">

                    <div class="overflow-x-auto">
                        <table class="desk-table-dense min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="desk-table-head">
                                        {{ __('Project') }}</th>
                                    <th class="desk-table-head">
                                        {{ __('Hours') }}</th>
                                    <th class="desk-table-head">
                                        {{ __('Billable') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($dashboard['project_overview'] as $project)
                                    <tr class="desk-interactive-row">
                                        <td class="desk-table-cell">
                                            <div class="font-mono text-xs text-gray-500">{{ $project['code'] }}
                                            </div>
                                            <div>{{ $project['name'] }}</div>
                                        </td>
                                        <td class="desk-table-cell">
                                            @deskDuration($project['duration_minutes'])</td>
                                        <td class="desk-table-cell">
                                            @deskMoney((float) $project['billable_amount'])</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="desk-table-empty" colspan="3">
                                            <div class="desk-empty-panel">
                                                {{ __('No project metrics available for the current reporting period.') }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-desk.table-card>
            </div>
        </div>
    </div>

    <script>
        (function() {
            if (!window.ApexCharts) {
                return;
            }

            const container = document.querySelector('#monthly-cashflow-chart');

            if (!container) {
                return;
            }

            const options = {
                chart: {
                    type: 'line',
                    height: 300,
                    toolbar: {
                        show: false,
                    },
                },
                stroke: {
                    curve: 'smooth',
                    width: 3,
                },
                series: [{
                    name: 'Net Cashflow',
                    data: @json($dashboard['monthly_cashflow']['values']),
                }],
                xaxis: {
                    categories: @json($dashboard['monthly_cashflow']['labels']),
                },
                colors: ['#111827'],
                dataLabels: {
                    enabled: false,
                },
                grid: {
                    borderColor: '#E5E7EB',
                },
            };

            new window.ApexCharts(container, options).render();

            const renderDonut = (selector, labels, values, colors) => {
                const chartContainer = document.querySelector(selector);

                if (!chartContainer) {
                    return;
                }

                new window.ApexCharts(chartContainer, {
                    chart: {
                        type: 'donut',
                        height: 300,
                    },
                    labels: labels,
                    series: values,
                    legend: {
                        position: 'bottom',
                    },
                    colors: colors,
                    dataLabels: {
                        enabled: false,
                    },
                }).render();
            };

            renderDonut(
                '#expense-breakdown-chart',
                @json($dashboard['expense_breakdown']['labels']),
                @json($dashboard['expense_breakdown']['values']),
                ['#DC2626', '#EA580C', '#D97706', '#65A30D', '#0EA5E9', '#7C3AED']
            );

            renderDonut(
                '#income-breakdown-chart',
                @json($dashboard['income_breakdown']['labels']),
                @json($dashboard['income_breakdown']['values']),
                ['#16A34A', '#0284C7', '#4F46E5', '#C026D3', '#0891B2', '#15803D']
            );

            const renderBar = (selector, labels, values, color) => {
                const chartContainer = document.querySelector(selector);

                if (!chartContainer) {
                    return;
                }

                new window.ApexCharts(chartContainer, {
                    chart: {
                        type: 'bar',
                        height: 300,
                        toolbar: {
                            show: false,
                        },
                    },
                    series: [{
                        name: 'Billable',
                        data: values,
                    }],
                    xaxis: {
                        categories: labels,
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    colors: [color],
                }).render();
            };

            renderBar(
                '#billable-by-project-chart',
                @json($dashboard['billable_by_project']['labels']),
                @json($dashboard['billable_by_project']['values']),
                '#1D4ED8'
            );

            renderBar(
                '#billable-by-stage-chart',
                @json($dashboard['billable_by_stage']['labels']),
                @json($dashboard['billable_by_stage']['values']),
                '#0F766E'
            );
        })();
    </script>
</x-app-layout>
