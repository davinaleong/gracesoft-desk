<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transactions Import Preview') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($missingHeaders !== [])
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-4">
                    {{ __('Missing required headers:') }} {{ implode(', ', $missingHeaders) }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm text-gray-700">
                        {{ __('Valid rows:') }} <span class="font-semibold">{{ count($validRows) }}</span>
                        <span class="mx-2">|</span>
                        {{ __('Invalid rows:') }} <span class="font-semibold">{{ count($invalidRows) }}</span>
                    </p>

                    <form method="POST" action="{{ route('transactions.import.commit') }}">
                        @csrf
                        <x-primary-button :disabled="$validRows === []">{{ __('Commit Import') }}</x-primary-button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Valid Rows Preview') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Code') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Date') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Type') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Status') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($validRows as $row)
                                <tr>
                                    <td class="px-3 py-2 text-sm font-mono">{{ $row['transaction_code'] ?? 'AUTO' }}
                                    </td>
                                    <td class="px-3 py-2 text-sm">@deskDate($row['transaction_date'])</td>
                                    <td class="px-3 py-2 text-sm">{{ $row['type'] }} / {{ $row['direction'] }}</td>
                                    <td class="px-3 py-2 text-sm">{{ $row['status'] }}</td>
                                    <td class="px-3 py-2 text-sm">@deskMoney((float) $row['amount'])</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-4 text-sm text-gray-500" colspan="5">
                                        {{ __('No valid rows to import.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($invalidRows !== [])
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Invalid Rows') }}</h3>
                    <ul class="space-y-3">
                        @foreach ($invalidRows as $row)
                            <li class="text-sm text-red-700">
                                {{ __('Line') }} {{ $row['line'] }}: {{ implode('; ', $row['errors']) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <a href="{{ route('transactions.import.create') }}"
                    class="text-sm text-gray-600 hover:text-gray-900">{{ __('Upload Another File') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
