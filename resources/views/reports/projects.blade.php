<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <img src="{{ asset('wm.svg') }}" alt="GraceSoft Desk" class="h-3.5 w-auto">
                <div class="flex items-center gap-3">
                    <h2 class="desk-page-title">{{ __('Project Report') }}</h2>
                    <span class="desk-context-chip">{{ __('Projects') }}</span>
                </div>
            </div>
            <div class="desk-toolbar-actions">
                <a href="{{ route('reports.projects.export', request()->only(['from', 'to'])) }}"
                    class="desk-action-secondary">
                    {{ __('Export CSV') }}
                </a>
                <a href="{{ route('reports.projects.print', request()->only(['from', 'to'])) }}" target="_blank"
                    class="desk-action-primary">
                    {{ __('Print') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="desk-page-shell">
        <div class="desk-page-container desk-stack">
            <div class="desk-card-muted p-6">
                <form method="GET" action="{{ route('reports.projects') }}" class="desk-filter-grid">
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
                    <p class="desk-kpi-label">{{ __('Active Projects') }}</p>
                    <p class="desk-kpi-value">{{ $report['totals']['active_projects'] }}</p>
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Total Hours') }}</p>
                    <p class="desk-kpi-value">
                        {{ number_format((float) $report['totals']['total_hours'], 2) }}h</p>
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Total Billable') }}</p>
                    <p class="desk-kpi-value">
                        @deskMoney((float) $report['totals']['total_billable'])</p>
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Total Stages') }}</p>
                    <p class="desk-kpi-value">{{ $report['totals']['total_stages'] }}</p>
                </div>
            </div>

            <div class="desk-card desk-card-body">
                <h3 class="desk-card-title">{{ __('Project Summary') }}</h3>
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
                            @forelse ($report['project_summary'] as $row)
                                <tr class="desk-interactive-row">
                                    <td class="desk-table-cell"><span class="font-mono">{{ $row->code }}</span> -
                                        {{ $row->name }}</td>
                                    <td class="desk-table-cell">@deskDuration((int) $row->duration_minutes)</td>
                                    <td class="desk-table-cell">@deskMoney((float) $row->billable_amount)
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="desk-table-empty" colspan="3">
                                        <div class="desk-empty-panel">
                                            {{ __('No time entry data in selected range.') }}
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
