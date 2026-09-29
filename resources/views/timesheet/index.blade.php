<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Weekly Timesheet') }}
            </h2>

            <nav class="flex items-center gap-2 text-sm" aria-label="{{ __('Week navigation') }}">
                <a href="{{ route('timesheet.index', ['week' => $weekStart->subWeek()->toDateString()]) }}" class="px-3 py-1.5 bg-white border border-gray-300 rounded-md hover:bg-gray-50">&larr; {{ __('Previous') }}</a>
                <span class="font-medium">{{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}</span>
                <a href="{{ route('timesheet.index', ['week' => $weekStart->addWeek()->toDateString()]) }}" class="px-3 py-1.5 bg-white border border-gray-300 rounded-md hover:bg-gray-50">{{ __('Next') }} &rarr;</a>
                <a href="{{ route('timesheet.index') }}" class="px-3 py-1.5 text-indigo-600 hover:underline">{{ __('This week') }}</a>
            </nav>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (is_string(session('status')) && session('status') !== 'archive-mode-read-only')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            @php
                $loggedHours = round($loggedMinutes / 60, 2);
                $pendingCount = collect($days)->flatten(1)->sum(fn ($group) => count($group['commits']));
            @endphp
            <div class="desk-kpi-grid !mb-0 md:grid-cols-3 xl:grid-cols-3">
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Logged This Week') }}</p>
                    <p class="desk-kpi-value">{{ number_format($loggedHours, 2) }} h
                        @if ($targetHours)
                            <span class="text-sm text-gray-500">/ {{ number_format($targetHours, 2) }} h</span>
                        @endif
                    </p>
                    @if ($targetHours)
                        <div class="mt-2 h-2 rounded bg-gray-200" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                            aria-valuenow="{{ min(100, (int) round($loggedHours / $targetHours * 100)) }}" aria-label="{{ __('Progress toward weekly target') }}">
                            <div class="h-2 rounded bg-indigo-600" style="width: {{ min(100, (int) round($loggedHours / $targetHours * 100)) }}%"></div>
                        </div>
                    @endif
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Pending Commits') }}</p>
                    <p class="desk-kpi-value-pending">{{ $pendingCount }}</p>
                </div>
                <div class="desk-kpi-card">
                    <p class="desk-kpi-label">{{ __('Suggested (15 min each)') }}</p>
                    <p class="desk-kpi-value">{{ number_format($pendingCount * 0.25, 2) }} h</p>
                </div>
            </div>

            @if ($days === [])
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-500">{{ __('No pending commits this week.') }}</div>
            @else
                <form method="POST" action="{{ route('timesheet.convert') }}" x-data="{ submitting: false }" @submit="submitting = true" class="space-y-6">
                    @csrf
                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">

                    <div class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-center gap-4 text-sm sticky top-0 z-10">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" x-on:change="$root.querySelectorAll('input[name=\'selected[]\']').forEach(el => el.checked = $event.target.checked)"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ __('Select all') }}
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="group" value="0">
                            <input type="checkbox" name="group" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ __('One entry per day & project') }}
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_billable" value="0">
                            <input type="checkbox" name="is_billable" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ __('Billable (billable projects only)') }}
                        </label>
                        <div class="flex items-center gap-2 ml-auto">
                            <x-primary-button x-bind:disabled="submitting">{{ __('Convert Selected') }}</x-primary-button>
                            <button type="submit" formaction="{{ route('timesheet.squash') }}"
                                class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">{{ __('Squash') }}</button>
                            <button type="submit" formaction="{{ route('timesheet.dismiss') }}"
                                class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-red-700 uppercase tracking-widest hover:bg-red-50">{{ __('Dismiss') }}</button>
                        </div>
                    </div>

                    @foreach ($days as $day => $projects)
                        <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4" aria-labelledby="day-{{ $day }}">
                            <h3 id="day-{{ $day }}" class="text-sm font-semibold text-gray-700">
                                {{ $day === 'undated' ? __('Undated') : \Carbon\CarbonImmutable::parse($day)->format('l, d M Y') }}
                            </h3>

                            @foreach ($projects as $group)
                                <div>
                                    <p class="text-xs font-semibold uppercase text-gray-500 mb-2">{{ $group['project']->code }} — {{ $group['project']->name }}</p>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                                            <caption class="sr-only">{{ __('Pending commits for :project on :day', ['project' => $group['project']->code, 'day' => $day]) }}</caption>
                                            <thead>
                                                <tr class="text-xs text-gray-500 uppercase">
                                                    <th scope="col" class="px-2 py-1 text-left w-8"><span class="sr-only">{{ __('Select') }}</span></th>
                                                    <th scope="col" class="px-2 py-1 text-left">{{ __('Commit') }}</th>
                                                    <th scope="col" class="px-2 py-1 text-left w-48">{{ __('Stage') }}</th>
                                                    <th scope="col" class="px-2 py-1 text-left">{{ __('Summary') }}</th>
                                                    <th scope="col" class="px-2 py-1 text-left w-24">{{ __('Minutes') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-50">
                                                @foreach ($group['commits'] as $row)
                                                    @php($commit = $row['commit'])
                                                    <tr>
                                                        <td class="px-2 py-2 align-top">
                                                            <input id="commit-{{ $commit->uuid }}" type="checkbox" name="selected[]" value="{{ $commit->uuid }}"
                                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        </td>
                                                        <td class="px-2 py-2 align-top">
                                                            <label for="commit-{{ $commit->uuid }}" class="block font-mono text-xs text-gray-500">{{ substr($commit->sha, 0, 7) }} · {{ $commit->committed_at?->format('H:i') }}</label>
                                                            <span class="block">{{ \Illuminate\Support\Str::limit($commit->message, 90) }}</span>
                                                        </td>
                                                        <td class="px-2 py-2 align-top">
                                                            <label class="sr-only" for="stage-{{ $commit->uuid }}">{{ __('Stage') }}</label>
                                                            <select id="stage-{{ $commit->uuid }}" name="rows[{{ $commit->uuid }}][stage_uuid]"
                                                                class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                                                <option value="">{{ __('No stage') }}</option>
                                                                @foreach ($stages as $stage)
                                                                    <option value="{{ $stage->uuid }}" @selected($row['stage']?->id === $stage->id)>{{ $stage->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td class="px-2 py-2 align-top">
                                                            <label class="sr-only" for="notes-{{ $commit->uuid }}">{{ __('Summary') }}</label>
                                                            <input id="notes-{{ $commit->uuid }}" type="text" name="rows[{{ $commit->uuid }}][notes]" value="{{ $row['summary'] }}"
                                                                class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                                        </td>
                                                        <td class="px-2 py-2 align-top">
                                                            <label class="sr-only" for="minutes-{{ $commit->uuid }}">{{ __('Minutes') }}</label>
                                                            <input id="minutes-{{ $commit->uuid }}" type="number" min="15" step="15" name="rows[{{ $commit->uuid }}][minutes]" value="{{ $row['minutes'] }}"
                                                                class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </section>
                    @endforeach
                </form>
            @endif

            @if ($dismissed->isNotEmpty())
                <details class="bg-white shadow-sm sm:rounded-lg p-6">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-700">{{ __('Dismissed this week (:count)', ['count' => $dismissed->count()]) }}</summary>
                    <form method="POST" action="{{ route('timesheet.restore') }}" class="mt-4 space-y-2">
                        @csrf
                        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                        @foreach ($dismissed as $commit)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="selected[]" value="{{ $commit->uuid }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="font-mono text-xs text-gray-500">{{ $commit->project?->code }} {{ substr($commit->sha, 0, 7) }}</span>
                                <span>{{ \Illuminate\Support\Str::limit($commit->message, 90) }}</span>
                            </label>
                        @endforeach
                        <x-secondary-button type="submit">{{ __('Restore Selected') }}</x-secondary-button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
