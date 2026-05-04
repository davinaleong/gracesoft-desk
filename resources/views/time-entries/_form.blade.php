@csrf

<div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="project_uuid" :value="__('Project')" />
            <select id="project_uuid" name="project_uuid"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                @foreach ($projects as $project)
                    <option value="{{ $project->uuid }}" @selected(old('project_uuid', $timeEntry->project?->uuid ?? '') === $project->uuid)>
                        {{ $project->code }} - {{ $project->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('project_uuid')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="project_stage_uuid" :value="__('Stage (Optional)')" />
            <select id="project_stage_uuid" name="project_stage_uuid"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">{{ __('None') }}</option>
                @foreach ($stages as $stage)
                    <option value="{{ $stage->uuid }}" @selected(old('project_stage_uuid', $timeEntry->stage?->uuid ?? '') === $stage->uuid)>
                        {{ $stage->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('project_stage_uuid')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-input-label for="entry_date" :value="__('Entry Date')" />
            <x-text-input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full" :value="old(
                'entry_date',
                isset($timeEntry) && $timeEntry->entry_date
                    ? $timeEntry->entry_date->toDateString()
                    : now()->toDateString(),
            )"
                required />
            <x-input-error :messages="$errors->get('entry_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="duration_minutes" :value="__('Duration (Minutes)')" />
            <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="1"
                class="mt-1 block w-full" :value="old('duration_minutes', $timeEntry->duration_minutes ?? 60)" required />
            <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="hourly_rate" :value="__('Hourly Rate')" />
            <x-text-input id="hourly_rate" name="hourly_rate" type="number" min="0" step="0.01"
                class="mt-1 block w-full" :value="old('hourly_rate', $timeEntry->hourly_rate ?? '0.00')" />
            <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="is_billable" value="0">
        <input id="is_billable" name="is_billable" type="checkbox" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
            @checked(old('is_billable', $timeEntry->is_billable ?? true))>
        <x-input-label for="is_billable" :value="__('Billable Entry')" />
    </div>

    <div>
        <x-input-label for="notes" :value="__('Notes')" />
        <textarea id="notes" name="notes" rows="4"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $timeEntry->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button x-bind:disabled="submitting">
        <span x-show="!submitting">{{ $submitLabel }}</span>
        <span x-show="submitting">{{ __('Saving...') }}</span>
    </x-primary-button>
    <a href="{{ route('time-entries.index') }}"
        class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
</div>
