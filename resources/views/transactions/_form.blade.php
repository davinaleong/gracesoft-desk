@csrf

<div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="account_id" :value="__('Account')" />
            <select id="account_id" name="account_id"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((int) old('account_id', $transaction->account_id ?? 0) === $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="transaction_date" :value="__('Date')" />
            <x-text-input id="transaction_date" name="transaction_date" type="date" class="mt-1 block w-full"
                :value="old(
                    'transaction_date',
                    isset($transaction) && $transaction->transaction_date
                        ? $transaction->transaction_date->toDateString()
                        : now()->toDateString(),
                )" required />
            <x-input-error :messages="$errors->get('transaction_date')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-input-label for="type" :value="__('Type')" />
            <select id="type" name="type"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                @foreach (['income', 'expense', 'transfer'] as $value)
                    <option value="{{ $value }}" @selected(old('type', $transaction->type ?? 'expense') === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="direction" :value="__('Direction')" />
            <select id="direction" name="direction"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                @foreach (['in', 'out'] as $value)
                    <option value="{{ $value }}" @selected(old('direction', $transaction->direction ?? 'out') === $value)>{{ strtoupper($value) }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('direction')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="status" :value="__('Status')" />
            <select id="status" name="status"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                required>
                @foreach (['pending', 'completed', 'void'] as $value)
                    <option value="{{ $value }}" @selected(old('status', $transaction->status ?? 'completed') === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="transaction_category_id" :value="__('Category')" />
            <select id="transaction_category_id" name="transaction_category_id"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">{{ __('None') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('transaction_category_id', $transaction->transaction_category_id ?? 0) === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('transaction_category_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="payment_method_id" :value="__('Payment Method')" />
            <select id="payment_method_id" name="payment_method_id"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">{{ __('None') }}</option>
                @foreach ($paymentMethods as $paymentMethod)
                    <option value="{{ $paymentMethod->id }}" @selected((int) old('payment_method_id', $transaction->payment_method_id ?? 0) === $paymentMethod->id)>{{ $paymentMethod->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('payment_method_id')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="project_id" :value="__('Project (Optional)')" />
        <select id="project_id" name="project_id"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">{{ __('None') }}</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected((int) old('project_id', $transaction->project_id ?? 0) === $project->id)>{{ $project->code }} -
                    {{ $project->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('project_id')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="amount" :value="__('Amount')" />
            <x-text-input id="amount" name="amount" type="number" step="0.01" min="0"
                class="mt-1 block w-full" :value="old('amount', $transaction->amount ?? '0.00')" required />
            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="gst_amount" :value="__('GST Amount')" />
            <x-text-input id="gst_amount" name="gst_amount" type="number" step="0.01" min="0"
                class="mt-1 block w-full" :value="old('gst_amount', $transaction->gst_amount ?? '0.00')" required />
            <x-input-error :messages="$errors->get('gst_amount')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="reference" :value="__('Reference')" />
        <x-text-input id="reference" name="reference" type="text" class="mt-1 block w-full" :value="old('reference', $transaction->reference ?? '')" />
        <x-input-error :messages="$errors->get('reference')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" :value="__('Description')" />
        <textarea id="description" name="description" rows="3"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $transaction->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
    <a href="{{ route('transactions.index') }}"
        class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
</div>
