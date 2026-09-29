{{-- Repeatable manual line rows. Expects $initialLines (array of description/quantity/unit_price). --}}
<div x-data="{ lines: {{ Js::from(array_values($initialLines)) }} }" class="space-y-3">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">{{ __('Manual Lines') }}</h3>
        <button type="button" @click="lines.push({ description: '', quantity: '1', unit_price: '' })"
            class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
            {{ __('Add Line') }}
        </button>
    </div>

    <template x-for="(line, index) in lines" :key="index">
        <div class="grid grid-cols-12 gap-2 items-start">
            <div class="col-span-12 sm:col-span-7">
                <label class="sr-only" :for="'manual-desc-' + index">{{ __('Description') }}</label>
                <input type="text" :id="'manual-desc-' + index" :name="'manual_lines[' + index + '][description]'" x-model="line.description"
                    placeholder="{{ __('Description') }}" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
            </div>
            <div class="col-span-4 sm:col-span-2">
                <label class="sr-only" :for="'manual-qty-' + index">{{ __('Quantity') }}</label>
                <input type="number" step="0.01" min="0.01" :id="'manual-qty-' + index" :name="'manual_lines[' + index + '][quantity]'" x-model="line.quantity"
                    placeholder="{{ __('Qty') }}" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="sr-only" :for="'manual-price-' + index">{{ __('Unit Price') }}</label>
                <input type="number" step="0.01" min="0" :id="'manual-price-' + index" :name="'manual_lines[' + index + '][unit_price]'" x-model="line.unit_price"
                    placeholder="{{ __('Unit price') }}" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
            </div>
            <div class="col-span-2 sm:col-span-1 pt-2 text-right">
                <button type="button" @click="lines.splice(index, 1)" class="text-sm text-red-600 hover:text-red-800">{{ __('Remove') }}</button>
            </div>
        </div>
    </template>

    <p x-show="lines.length === 0" class="text-sm text-gray-500">{{ __('No manual lines. Use these for fixed fees or expenses.') }}</p>
    <x-input-error :messages="collect($errors->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'manual_lines'))->flatten()->all()" class="mt-2" />
</div>
