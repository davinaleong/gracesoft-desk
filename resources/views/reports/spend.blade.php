<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <img src="{{ asset('wm.svg') }}" alt="GraceSoft Desk" class="h-3.5 w-auto">
                <div class="flex items-center gap-3">
                    <h2 class="desk-page-title">{{ __('Service Spend Report') }}</h2>
                    <span class="desk-context-chip">{{ __('Services') }}</span>
                </div>
            </div>
            <div class="desk-toolbar-actions">
                <a href="{{ route('reports.spend.export', request()->only(['from', 'to'])) }}" class="desk-action-secondary">
                    {{ __('Export CSV') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="desk-page-shell">
        <div class="desk-page-container desk-stack">
            <div class="desk-card-muted p-6">
                <form method="GET" action="{{ route('reports.spend') }}" class="desk-filter-grid">
                    <div>
                        <x-input-label for="from" :value="__('From')" />
                        <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="$report['range']['from']" />
                    </div>
                    <div>
                        <x-input-label for="to" :value="__('To')" />
                        <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" :value="$report['range']['to']" />
                    </div>
                    <div class="sm:self-end">
                        <x-primary-button>{{ __('Apply Filter') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="desk-kpi-grid !mb-0 md:grid-cols-2 xl:grid-cols-2">
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Service Spend (completed)') }}</p>
                    <p class="desk-kpi-value-negative">@deskMoney($report['total'])</p>
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Vendors') }}</p>
                    <p class="desk-kpi-value">{{ count($report['by_vendor']) }}</p>
                </div>
            </div>

            <div class="desk-report-grid">
                @foreach (['by_vendor' => [__('By Vendor'), 'vendor'], 'by_category' => [__('By Category'), 'category']] as $key => [$title, $labelKey])
                    <div class="desk-card desk-card-body">
                        <h3 class="desk-card-title">{{ $title }}</h3>
                        <table class="desk-table-dense min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="desk-table-head">{{ $labelKey === 'vendor' ? __('Vendor') : __('Category') }}</th>
                                    <th class="desk-table-head">{{ __('Payments') }}</th>
                                    <th class="desk-table-head">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($report[$key] as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-sm">{{ $row[$labelKey] }}</td>
                                        <td class="px-3 py-2 text-sm">{{ $row['count'] }}</td>
                                        <td class="px-3 py-2 text-sm">@deskMoney($row['total'])</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-3 py-4 text-sm text-gray-500">{{ __('No completed service payments in this range.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
