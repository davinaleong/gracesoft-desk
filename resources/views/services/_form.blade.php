@csrf

<div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="vendor_uuid" :value="__('Vendor')" />
            <select id="vendor_uuid" name="vendor_uuid"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                <option value="">{{ __('Select a vendor') }}</option>
                @foreach ($vendors as $vendorOption)
                    <option value="{{ $vendorOption->uuid }}" @selected(old('vendor_uuid', $service->vendor?->uuid ?? request('vendor_uuid', '')) === $vendorOption->uuid)>
                        {{ $vendorOption->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('vendor_uuid')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $service->name ?? '')"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-input-label for="plan" :value="__('Plan / Tier')" />
            <x-text-input id="plan" name="plan" type="text" class="mt-1 block w-full" :value="old('plan', $service->plan ?? '')" />
            <x-input-error :messages="$errors->get('plan')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="category_id" :value="__('Category')" />
            <select id="category_id" name="category_id"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                <option value="">{{ __('Select a category') }}</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption->id }}" @selected((int) old('category_id', $service->category_id ?? '') === $categoryOption->id)>
                        {{ $categoryOption->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="status" :value="__('Status')" />
            <select id="status" name="status"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                @foreach (['active', 'paused', 'cancelled'] as $value)
                    <option value="{{ $value }}" @selected(old('status', $service->status ?? 'active') === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="notes" :value="__('Notes')" />
        <textarea id="notes" name="notes" rows="4"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $service->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button :disabled="isset($submitting)">{{ $submitLabel }}</x-primary-button>
    </div>
</div>
