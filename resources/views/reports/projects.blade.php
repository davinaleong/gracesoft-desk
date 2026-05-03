<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Project Report') }}</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.projects.export', request()->only(['from', 'to'])) }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Export CSV') }}
                </a>
                <a href="{{ route('reports.projects.print', request()->only(['from', 'to'])) }}" target="_blank"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Print') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('reports.projects') }}"
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
                    <p class="text-sm text-gray-500">{{ __('Active Projects') }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $report['totals']['active_projects'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Total Hours') }}</p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ number_format((float) $report['totals']['total_hours'], 2) }}h</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Total Billable') }}</p>
                    <p class="mt-2 text-2xl font-semibold">
                        @deskMoney((float) $report['totals']['total_billable'])</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ __('Total Stages') }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $report['totals']['total_stages'] }}</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Project Summary') }}</h3>
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
                            @forelse ($report['project_summary'] as $row)
                                <tr>
                                    <td class="px-3 py-2 text-sm"><span class="font-mono">{{ $row->code }}</span> -
                                        {{ $row->name }}</td>
                                    <td class="px-3 py-2 text-sm">@deskDuration((int) $row->duration_minutes)</td>
                                    <td class="px-3 py-2 text-sm">@deskMoney((float) $row->billable_amount)
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-4 text-sm text-gray-500" colspan="3">
                                        {{ __('No time entry data in selected range.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
