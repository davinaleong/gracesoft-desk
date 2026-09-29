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

    <fieldset class="border-t border-gray-200 pt-4 space-y-4">
        <legend class="text-sm font-semibold text-gray-700">{{ __('Renewal') }}</legend>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <x-input-label for="billing_cycle" :value="__('Billing Cycle')" />
                <select id="billing_cycle" name="billing_cycle"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">{{ __('Not tracked') }}</option>
                    @foreach (['monthly' => __('Monthly'), 'yearly' => __('Yearly')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('billing_cycle', $service->billing_cycle ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('billing_cycle')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="expected_amount" :value="__('Expected Amount')" />
                <x-text-input id="expected_amount" name="expected_amount" type="number" min="0" step="0.01" class="mt-1 block w-full"
                    :value="old('expected_amount', $service->expected_amount ?? '')" />
                <x-input-error :messages="$errors->get('expected_amount')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="currency" :value="__('Currency')" />
                <x-text-input id="currency" name="currency" type="text" maxlength="3" class="mt-1 block w-full uppercase"
                    :value="old('currency', $service->currency ?? 'SGD')" />
                <x-input-error :messages="$errors->get('currency')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="next_renewal_date" :value="__('Next Renewal')" />
                <x-text-input id="next_renewal_date" name="next_renewal_date" type="date" class="mt-1 block w-full"
                    :value="old('next_renewal_date', isset($service) && $service->next_renewal_date ? $service->next_renewal_date->toDateString() : '')" />
                <x-input-error :messages="$errors->get('next_renewal_date')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <x-input-label for="account_uuid" :value="__('Paid From Account')" />
                <select id="account_uuid" name="account_uuid"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->uuid }}" @selected(old('account_uuid', ($service ?? null)?->account?->uuid ?? '') === $account->uuid)>{{ $account->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('account_uuid')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="payment_method_uuid" :value="__('Payment Method')" />
                <select id="payment_method_uuid" name="payment_method_uuid"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->uuid }}" @selected(old('payment_method_uuid', ($service ?? null)?->paymentMethod?->uuid ?? '') === $method->uuid)>{{ $method->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="transaction_category_uuid" :value="__('Expense Category')" />
                <select id="transaction_category_uuid" name="transaction_category_uuid"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($expenseCategories as $expenseCategory)
                        <option value="{{ $expenseCategory->uuid }}" @selected(old('transaction_category_uuid', ($service ?? null)?->transactionCategory?->uuid ?? '') === $expenseCategory->uuid)>{{ $expenseCategory->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="reminder_days_before" :value="__('Remind Days Before')" />
                <x-text-input id="reminder_days_before" name="reminder_days_before" type="number" min="0" max="90" class="mt-1 block w-full"
                    :value="old('reminder_days_before', $service->reminder_days_before ?? 7)" />
                <x-input-error :messages="$errors->get('reminder_days_before')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <input type="hidden" name="auto_create_expense" value="0">
            <input id="auto_create_expense" name="auto_create_expense" type="checkbox" value="1"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                @checked(old('auto_create_expense', $service->auto_create_expense ?? false))>
            <x-input-label for="auto_create_expense" :value="__('Create a pending expense on each renewal date')" />
        </div>
    </fieldset>

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
