@csrf

<div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $vendor->name ?? '')"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="category_id" :value="__('Category')" />
            <select id="category_id" name="category_id"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                <option value="">{{ __('Select a category') }}</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption->id }}" @selected((int) old('category_id', $vendor->category_id ?? '') === $categoryOption->id)>
                        {{ $categoryOption->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="website" :value="__('Website')" />
            <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $vendor->website ?? '')" />
            <x-input-error :messages="$errors->get('website')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="support_url" :value="__('Support URL')" />
            <x-text-input id="support_url" name="support_url" type="url" class="mt-1 block w-full"
                :value="old('support_url', $vendor->support_url ?? '')" />
            <x-input-error :messages="$errors->get('support_url')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="account_number" :value="__('Account Number')" />
            <x-text-input id="account_number" name="account_number" type="text" class="mt-1 block w-full"
                :value="old('account_number', $vendor->account_number ?? '')" />
            <x-input-error :messages="$errors->get('account_number')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="status" :value="__('Status')" />
            <select id="status" name="status"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                @foreach (['active', 'inactive'] as $value)
                    <option value="{{ $value }}" @selected(old('status', $vendor->status ?? 'active') === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="notes" :value="__('Notes')" />
        <textarea id="notes" name="notes" rows="4"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $vendor->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button :disabled="isset($submitting)">{{ $submitLabel }}</x-primary-button>
    </div>
</div>
