@csrf

<div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $client->name ?? '')" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="billing_email" :value="__('Billing Email')" />
            <x-text-input id="billing_email" name="billing_email" type="email" class="mt-1 block w-full"
                :value="old('billing_email', $client->billing_email ?? '')" />
            <x-input-error :messages="$errors->get('billing_email')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="address" :value="__('Address')" />
        <textarea id="address" name="address" rows="3"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $client->address ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('address')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-input-label for="tax_id" :value="__('Company Reg. / Tax ID (Optional)')" />
            <x-text-input id="tax_id" name="tax_id" type="text" class="mt-1 block w-full" :value="old('tax_id', $client->tax_id ?? '')" />
            <x-input-error :messages="$errors->get('tax_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="currency" :value="__('Currency')" />
            <x-text-input id="currency" name="currency" type="text" maxlength="3" class="mt-1 block w-full uppercase"
                :value="old('currency', $client->currency ?? $defaultCurrency)" required />
            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="default_hourly_rate" :value="__('Default Hourly Rate')" />
            <x-text-input id="default_hourly_rate" name="default_hourly_rate" type="number" min="0" step="0.01"
                class="mt-1 block w-full" :value="old('default_hourly_rate', $client->default_hourly_rate ?? '')" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Used when a project has no rate of its own.') }}</p>
            <x-input-error :messages="$errors->get('default_hourly_rate')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="status" :value="__('Status')" />
        <select id="status" name="status"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach (\App\Models\Client::STATUSES as $value)
                <option value="{{ $value }}" @selected(old('status', $client->status ?? 'active') === $value)>{{ ucfirst($value) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="notes" :value="__('Notes')" />
        <textarea id="notes" name="notes" rows="4"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $client->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button x-bind:disabled="submitting">{{ $submitLabel }}</x-primary-button>
    </div>
</div>
