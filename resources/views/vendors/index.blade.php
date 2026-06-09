<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Vendors') }}
            </h2>

            <a href="{{ route('vendors.create') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New Vendor') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status') === 'vendor-deleted')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Vendor deleted.') }}
                </div>
            @endif

            {{-- Filters --}}
            <form method="GET" action="{{ route('vendors.index') }}" class="flex items-center gap-3">
                <select name="status"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach (['active', 'inactive'] as $statusOption)
                        <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ ucfirst($statusOption) }}
                        </option>
                    @endforeach
                </select>
                <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Filter') }}
                </button>
                @if (request('status'))
                    <a href="{{ route('vendors.index') }}"
                        class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <caption class="sr-only">{{ __('Vendors list') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Code') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Name') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Category') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Status') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Services') }}</th>
                                    <th scope="col" class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($vendors as $vendor)
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm">{{ $vendor->vendor_code }}</td>
                                        <td class="px-4 py-3 text-sm font-medium">{{ $vendor->name }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span
                                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                                {{ str_replace('_', ' ', ucfirst($vendor->category)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @if ($vendor->status === 'active')
                                                <span
                                                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                                            @else
                                                <span
                                                    class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">{{ $vendor->services_count }}</td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <a href="{{ route('vendors.show', $vendor) }}"
                                                class="text-blue-600 hover:text-blue-800">{{ __('View') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-sm text-gray-500" colspan="6">
                                            {{ __('No vendors yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $vendors->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
