<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Time Entries') }}
            </h2>

            <div class="flex items-center gap-2">
                <a href="{{ route('time-entries.import.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Import CSV') }}
                </a>
                <a href="{{ route('time-entries.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New Time Entry') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <caption class="sr-only">{{ __('Time entries list') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Date') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Project') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Stage') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Duration') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Billable') }}</th>
                                    <th scope="col" class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($timeEntries as $timeEntry)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">@deskDate($timeEntry->entry_date)</td>
                                        <td class="px-4 py-3 text-sm">{{ $timeEntry->project?->code }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $timeEntry->stage?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-sm">@deskDuration($timeEntry->duration_minutes)</td>
                                        <td class="px-4 py-3 text-sm">
                                            @deskMoney((float) $timeEntry->billable_amount)</td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <a href="{{ route('time-entries.show', $timeEntry) }}"
                                                aria-label="{{ __('View time entry for :project on :date', ['project' => $timeEntry->project?->code ?? 'N/A', 'date' => \App\Support\DeskFormat::date($timeEntry->entry_date)]) }}"
                                                class="text-blue-600 hover:text-blue-800">{{ __('View') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-sm text-gray-500" colspan="6">
                                            {{ __('No time entries yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $timeEntries->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
