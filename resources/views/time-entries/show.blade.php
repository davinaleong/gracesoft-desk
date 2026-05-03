<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Time Entry Details') }}
            </h2>

            <div class="flex items-center gap-3">
                <a href="{{ route('time-entries.edit', $timeEntry) }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Edit') }}
                </a>

                <form method="POST" action="{{ route('time-entries.destroy', $timeEntry) }}"
                    onsubmit="return confirm('{{ __('Delete this time entry?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Project') }}</dt>
                            <dd class="text-base">{{ $timeEntry->project?->code }} - {{ $timeEntry->project?->name }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Stage') }}</dt>
                            <dd class="text-base">{{ $timeEntry->stage?->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Entry Date') }}</dt>
                            <dd class="text-base">@deskDate($timeEntry->entry_date)</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Duration') }}</dt>
                            <dd class="text-base">@deskDuration($timeEntry->duration_minutes)</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Hourly Rate') }}</dt>
                            <dd class="text-base">@deskMoney((float) ($timeEntry->hourly_rate ?? 0))</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Billable Amount') }}</dt>
                            <dd class="text-base">@deskMoney((float) $timeEntry->billable_amount)</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-gray-500">{{ __('Notes') }}</dt>
                            <dd class="text-base whitespace-pre-line">{{ $timeEntry->notes ?: 'N/A' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6">
                        <a href="{{ route('time-entries.index') }}"
                            class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to Time Entries') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
