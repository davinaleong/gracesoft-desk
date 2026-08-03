<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Convert Commit to Time Entry') }}
            </h2>
            <a href="{{ route('projects.pending-commits.index', $project) }}"
                class="text-sm text-gray-600 hover:text-gray-900">{{ __('← Back to Pending Commits') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Commit context --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-3">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('Commit') }}</h3>
                    <div class="font-mono text-xs text-gray-500">{{ substr($commit->sha, 0, 8) }} · {{ $commit->branch }}</div>
                    <p class="text-sm text-gray-900">{{ $commit->message }}</p>
                    <div class="flex gap-4 text-xs text-gray-500">
                        <span>{{ $commit->author_name }} &lt;{{ $commit->author_email }}&gt;</span>
                        <span>{{ $commit->committed_at?->toDateTimeString() }}</span>
                        @if ($commit->additions !== null)
                            <span class="text-green-600">+{{ $commit->additions }}</span>
                            <span class="text-red-500">-{{ $commit->deletions ?? 0 }}</span>
                        @endif
                    </div>
                    @if ($suggestedStage)
                        <div class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                            {{ __('Suggested stage:') }} {{ $suggestedStage->name }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Conversion form --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST"
                        action="{{ route('projects.pending-commits.store', [$project, $commit]) }}"
                        x-data="{ submitting: false }"
                        @submit="submitting = true">
                        @csrf

                        <div class="space-y-4">
                            <div>
                                <x-input-label for="project_stage_uuid" :value="__('Stage (Optional)')" />
                                <select id="project_stage_uuid" name="project_stage_uuid"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($stages as $stage)
                                        <option value="{{ $stage->uuid }}"
                                            @selected(old('project_stage_uuid', $suggestedStage?->uuid) === $stage->uuid)>
                                            {{ $stage->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('project_stage_uuid')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="entry_date" :value="__('Entry Date')" />
                                    <x-text-input id="entry_date" name="entry_date" type="date"
                                        class="mt-1 block w-full"
                                        :value="old('entry_date', $commit->committed_at?->toDateString() ?? now()->toDateString())"
                                        required />
                                    <x-input-error :messages="$errors->get('entry_date')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="duration_minutes" :value="__('Duration (minutes, snapped to 15-min)')" />
                                    <x-text-input id="duration_minutes" name="duration_minutes" type="number"
                                        min="15" step="15" class="mt-1 block w-full"
                                        :value="old('duration_minutes', 30)" required />
                                    <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="hidden" name="is_billable" value="0">
                                <input id="is_billable" name="is_billable" type="checkbox" value="1"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    @checked(old('is_billable', $project->is_billable ? '1' : '0') === '1')>
                                <x-input-label for="is_billable" :value="__('Billable Entry')" />
                            </div>

                            <div>
                                <x-input-label for="notes" :value="__('Notes')" />
                                <textarea id="notes" name="notes" rows="3"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $commit->message) }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-3">
                            <x-primary-button x-bind:disabled="submitting">
                                <span x-show="!submitting">{{ __('Convert to Time Entry') }}</span>
                                <span x-show="submitting">{{ __('Saving...') }}</span>
                            </x-primary-button>
                            <a href="{{ route('projects.pending-commits.index', $project) }}"
                                class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
