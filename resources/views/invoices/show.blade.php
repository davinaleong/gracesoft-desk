<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Invoice') }} <span class="font-mono">{{ $invoice->displayNumber() }}</span>
            </h2>

            <div class="flex items-center gap-2">
                @if ($invoice->isDraft())
                    <a href="{{ route('invoices.edit', $invoice) }}"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        {{ __('Edit Draft') }}
                    </a>
                    <form method="POST" action="{{ route('invoices.issue', $invoice) }}" x-data="{ submitting: false }" @submit="submitting = true"
                        onsubmit="return confirm('{{ __('Issue this invoice? It gets a number and can no longer be edited.') }}')">
                        @csrf
                        <x-primary-button x-bind:disabled="submitting">{{ __('Issue Invoice') }}</x-primary-button>
                    </form>
                @else
                    <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        {{ __('PDF') }}
                    </a>
                    @if (in_array($invoice->status, ['issued', 'paid'], true))
                        <form method="POST" action="{{ route('invoices.send', $invoice) }}" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <x-primary-button x-bind:disabled="submitting">{{ $invoice->sent_at ? __('Resend') : __('Email to Client') }}</x-primary-button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @php
                $messages = [
                    'invoice-created' => __('Draft invoice created. Review the lines, then issue it.'),
                    'invoice-updated' => __('Draft invoice updated.'),
                    'invoice-issued' => __('Invoice issued.'),
                    'invoice-sent' => __('Invoice emailed to the client.'),
                    'invoice-paid' => __('Payment recorded and an income transaction created.'),
                    'invoice-voided' => __('Invoice voided. Its time entries are available to bill again.'),
                ];
            @endphp
            @if (isset($messages[session('status')]))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $messages[session('status')] }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->has('invoice'))
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('invoice') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Status') }}</p>
                        <p class="mt-1">@include('invoices._status', ['status' => $invoice->status])</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Client') }}</p>
                        <p class="text-base">
                            <a href="{{ route('clients.show', $invoice->client) }}" class="text-blue-600 hover:text-blue-800">{{ $invoice->client->name }}</a>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Issued / Due') }}</p>
                        <p class="text-base">
                            {{ $invoice->issue_date ? \App\Support\DeskFormat::date($invoice->issue_date) : '—' }} /
                            {{ $invoice->due_date ? \App\Support\DeskFormat::date($invoice->due_date) : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Sent') }}</p>
                        <p class="text-base">{{ $invoice->sent_at ? \App\Support\DeskFormat::date($invoice->sent_at) : '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Invoice lines') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Description') }}</th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Qty') }}</th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Unit Price') }}</th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Amount') }}</th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('GST') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($invoice->lines as $line)
                                    <tr>
                                        <td class="px-3 py-2">{{ $line->description }}</td>
                                        <td class="px-3 py-2 text-right">{{ $line->quantity }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $line->unit_price, 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $line->amount, 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $line->gst_amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-4 text-gray-500">{{ __('No lines.') }}</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="text-sm">
                                <tr>
                                    <td colspan="3" class="px-3 pt-4 text-right text-gray-500">{{ __('Subtotal') }}</td>
                                    <td colspan="2" class="px-3 pt-4 text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="px-3 text-right text-gray-500">{{ __('GST (:rate%)', ['rate' => (float) $invoice->gst_rate]) }}</td>
                                    <td colspan="2" class="px-3 text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->gst_amount, 2) }}</td>
                                </tr>
                                <tr class="font-semibold">
                                    <td colspan="3" class="px-3 text-right">{{ __('Total') }}</td>
                                    <td colspan="2" class="px-3 text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if ($invoice->notes)
                        <p class="mt-4 text-sm text-gray-700 whitespace-pre-line">{{ $invoice->notes }}</p>
                    @endif
                </div>
            </div>

            @if ($invoice->status === 'issued')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Record Payment') }}</h3>
                        <form method="POST" action="{{ route('invoices.payment', $invoice) }}" x-data="{ submitting: false }" @submit="submitting = true"
                            class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @csrf
                            <div>
                                <x-input-label for="account_uuid" :value="__('Received Into')" />
                                <select id="account_uuid" name="account_uuid" required
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->uuid }}" @selected(old('account_uuid') === $account->uuid)>{{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('account_uuid')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="payment_date" :value="__('Payment Date')" />
                                <x-text-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full" :value="old('payment_date', now()->toDateString())" required />
                                <x-input-error :messages="$errors->get('payment_date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="payment_method_uuid" :value="__('Payment Method (Optional)')" />
                                <select id="payment_method_uuid" name="payment_method_uuid"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method->uuid }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="transaction_category_uuid" :value="__('Category (Optional)')" />
                                <select id="transaction_category_uuid" name="transaction_category_uuid"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->uuid }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <x-primary-button x-bind:disabled="submitting">{{ __('Mark as Paid') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if ($invoice->paymentTransaction)
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Paid on :date.', ['date' => \App\Support\DeskFormat::date($invoice->paymentTransaction->transaction_date)]) }}
                    <a href="{{ route('transactions.show', $invoice->paymentTransaction) }}" class="font-semibold underline">{{ $invoice->paymentTransaction->transaction_code }}</a>
                </div>
            @endif

            @if ($invoice->status === 'void')
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ __('Voided on :date:', ['date' => \App\Support\DeskFormat::date($invoice->voided_at)]) }} {{ $invoice->void_reason }}
                </div>
            @endif

            @if (in_array($invoice->status, ['draft', 'issued'], true))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 space-y-4">
                        <form method="POST" action="{{ route('invoices.void', $invoice) }}" class="space-y-3"
                            onsubmit="return confirm('{{ __('Void this invoice? This cannot be undone.') }}')">
                            @csrf
                            <x-input-label for="void_reason" :value="__('Void Reason')" />
                            <x-text-input id="void_reason" name="void_reason" type="text" class="block w-full" :value="old('void_reason')" required />
                            <x-input-error :messages="$errors->get('void_reason')" class="mt-2" />
                            <x-danger-button>{{ __('Void Invoice') }}</x-danger-button>
                        </form>

                        @if ($invoice->isDraft())
                            <form method="POST" action="{{ route('invoices.destroy', $invoice) }}"
                                onsubmit="return confirm('{{ __('Delete this draft? Its time entries become available to bill again.') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">{{ __('Delete Draft') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
