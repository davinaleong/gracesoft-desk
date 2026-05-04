<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Transactions') }}
            </h2>

            <div class="flex items-center gap-2">
                <a href="{{ route('transactions.import.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Import CSV') }}
                </a>
                <a href="{{ route('transactions.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New Transaction') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <caption class="sr-only">{{ __('Transactions list') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Transaction') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Date') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Type') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Status') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Amount') }}</th>
                                    <th scope="col" class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($transactions as $transaction)
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm">{{ $transaction->transaction_code }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @deskDate($transaction->transaction_date)</td>
                                        <td class="px-4 py-3 text-sm">{{ $transaction->type }} /
                                            {{ $transaction->direction }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $transaction->status }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @deskMoney((float) $transaction->amount)</td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <a href="{{ route('transactions.show', $transaction) }}"
                                                aria-label="{{ __('View transaction :code', ['code' => $transaction->transaction_code]) }}"
                                                class="text-blue-600 hover:text-blue-800">{{ __('View') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-sm text-gray-500" colspan="6">
                                            {{ __('No transactions yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $transactions->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
