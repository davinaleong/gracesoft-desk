<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard') }}
            </h2>

            <span class="inline-flex items-center rounded-md bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                {{ now()->format('F Y') }}
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Active Projects') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $dashboard['kpis']['active_projects'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Total Logged Hours') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">
                        {{ number_format((float) $dashboard['kpis']['total_logged_hours'], 2) }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Total Billable Value') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">
                        {{ number_format((float) $dashboard['kpis']['total_billable_value'], 2) }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Net Cashflow (This Month)') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">
                        {{ number_format((float) $dashboard['kpis']['net_cashflow_this_month'], 2) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg xl:col-span-2">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Monthly Net Cashflow') }}</h3>
                        <div id="monthly-cashflow-chart" class="h-80"></div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Project Overview') }}</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            {{ __('Project') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            {{ __('Hours') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            {{ __('Billable') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($dashboard['project_overview'] as $project)
                                        <tr>
                                            <td class="px-3 py-2 text-sm">
                                                <div class="font-mono text-xs text-gray-500">{{ $project['code'] }}
                                                </div>
                                                <div>{{ $project['name'] }}</div>
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                {{ number_format($project['duration_minutes'] / 60, 2) }}</td>
                                            <td class="px-3 py-2 text-sm">
                                                {{ number_format((float) $project['billable_amount'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="px-3 py-4 text-sm text-gray-500" colspan="3">
                                                {{ __('No project metrics yet.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
        })();
    </script>
</x-app-layout>
