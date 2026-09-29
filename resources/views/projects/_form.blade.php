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
        <x-input-label for="client_uuid" :value="__('Client (Optional)')" />
        <select id="client_uuid" name="client_uuid"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">{{ __('None') }}</option>
            @foreach ($clients as $clientOption)
                <option value="{{ $clientOption->uuid }}" @selected(old('client_uuid', isset($project) ? ($project->client?->uuid ?? '') : '') === $clientOption->uuid)>{{ $clientOption->client_code }} - {{ $clientOption->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('client_uuid')" class="mt-2" />
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

    <div class="flex items-center gap-2">
        <input type="hidden" name="ai_opt_out" value="0">
        <input id="ai_opt_out" name="ai_opt_out" type="checkbox" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
            @checked(old('ai_opt_out', $project->ai_opt_out ?? false))>
        <x-input-label for="ai_opt_out" :value="__('Never send this project\'s commits to AI')" />
    </div>

    <div>
        <x-input-label for="hourly_rate" :value="__('Hourly Rate')" />
        <x-text-input id="hourly_rate" name="hourly_rate" type="number" min="0" step="0.01"
            class="mt-1 block w-full" :value="old('hourly_rate', $project->hourly_rate ?? '')" />
        <p class="mt-1 text-xs text-gray-500">{{ __('Leave blank to use the client\'s rate, then the system default.') }}</p>
        <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
    </div>

    <fieldset class="border-t border-gray-200 pt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <legend class="text-sm font-semibold text-gray-700">{{ __('Budget') }}</legend>

        <div>
            <x-input-label for="budget_type" :value="__('Budget Type')" />
            <select id="budget_type" name="budget_type"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                @foreach (['none' => __('No budget'), 'hours' => __('Hours'), 'amount' => __('Amount')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('budget_type', $project->budget_type ?? 'none') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('budget_type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="budget_value" :value="__('Budget (hours or amount)')" />
            <x-text-input id="budget_value" name="budget_value" type="number" min="0" step="0.01" class="mt-1 block w-full"
                :value="old('budget_value', $project->budget_value ?? '')" />
            <x-input-error :messages="$errors->get('budget_value')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="budget_thresholds" :value="__('Alert at (%)')" />
            <x-text-input id="budget_thresholds" name="budget_thresholds" type="text" class="mt-1 block w-full" placeholder="50, 80, 100"
                :value="implode(', ', (array) old('budget_thresholds', isset($project) ? ($project->budget_thresholds ?? []) : []))" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Blank uses 50, 80, 100. Changing the budget re-arms alerts.') }}</p>
            <x-input-error :messages="collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'budget_thresholds'))->flatten()->all()" class="mt-2" />
        </div>
    </fieldset>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button x-bind:disabled="submitting">
        <span x-show="!submitting">{{ $submitLabel }}</span>
        <span x-show="submitting">{{ __('Saving...') }}</span>
    </x-primary-button>
    <a href="{{ route('projects.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
</div>
