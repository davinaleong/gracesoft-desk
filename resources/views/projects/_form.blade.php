@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="code" :value="__('Project Code')" />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code', $project->code ?? '')" required />
        <x-input-error :messages="$errors->get('code')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="name" :value="__('Project Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $project->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="status" :value="__('Status')" />
        <select id="status" name="status"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            @foreach (['active', 'paused', 'completed', 'archived'] as $status)
                <option value="{{ $status }}" @selected(old('status', $project->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" :value="__('Description')" />
        <textarea id="description" name="description" rows="4"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $project->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="starts_on" :value="__('Starts On')" />
            <x-text-input id="starts_on" name="starts_on" type="date" class="mt-1 block w-full" :value="old(
                'starts_on',
                isset($project) && $project->starts_on ? $project->starts_on->toDateString() : '',
            )" />
            <x-input-error :messages="$errors->get('starts_on')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="ends_on" :value="__('Ends On')" />
            <x-text-input id="ends_on" name="ends_on" type="date" class="mt-1 block w-full" :value="old('ends_on', isset($project) && $project->ends_on ? $project->ends_on->toDateString() : '')" />
            <x-input-error :messages="$errors->get('ends_on')" class="mt-2" />
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="is_billable" value="0">
        <input id="is_billable" name="is_billable" type="checkbox" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
            @checked(old('is_billable', $project->is_billable ?? true))>
        <x-input-label for="is_billable" :value="__('Billable Project')" />
    </div>

    <div>
        <x-input-label for="hourly_rate" :value="__('Hourly Rate')" />
        <x-text-input id="hourly_rate" name="hourly_rate" type="number" min="0" step="0.01"
            class="mt-1 block w-full" :value="old('hourly_rate', $project->hourly_rate ?? '0.00')" />
        <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button x-bind:disabled="submitting">
        <span x-show="!submitting">{{ $submitLabel }}</span>
        <span x-show="submitting">{{ __('Saving...') }}</span>
    </x-primary-button>
    <a href="{{ route('projects.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
</div>
