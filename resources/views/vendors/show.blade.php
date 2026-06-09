<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Vendor Detail') }}
            </h2>

            <a href="{{ route('vendors.edit', $vendor) }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Edit Vendor') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'vendor-created')
                <div
                    class="flex items-center justify-between rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <span>{{ __('Vendor saved. Would you like to create another one?') }}</span>
                    <a href="{{ route('vendors.create') }}"
                        class="ml-4 font-semibold underline hover:text-blue-600">{{ __('Create Another') }}</a>
                </div>
            @endif

            @if (session('status') === 'vendor-updated')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Vendor updated successfully.') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Vendor Code') }}</p>
                        <p class="font-mono text-base">{{ $vendor->vendor_code }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Name') }}</p>
                        <p class="text-base font-semibold">{{ $vendor->name }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Category') }}</p>
                        <p class="text-base">
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                {{ str_replace('_', ' ', ucfirst($vendor->category)) }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Status') }}</p>
                        <p class="text-base">
                            @if ($vendor->status === 'active')
                                <span
                                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                            @else
                                <span
                                    class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">{{ __('Inactive') }}</span>
                            @endif
                        </p>
                    </div>

                    @if ($vendor->website)
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __('Website') }}</p>
                            <p class="text-base">
                                <a href="{{ $vendor->website }}" target="_blank" rel="noopener noreferrer"
                                    class="text-blue-600 hover:text-blue-800">{{ $vendor->website }}</a>
                            </p>
                        </div>
                    @endif

                    @if ($vendor->support_url)
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __('Support URL') }}</p>
                            <p class="text-base">
                                <a href="{{ $vendor->support_url }}" target="_blank" rel="noopener noreferrer"
                                    class="text-blue-600 hover:text-blue-800">{{ $vendor->support_url }}</a>
                            </p>
                        </div>
                    @endif

                    @if ($vendor->account_number)
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __('Account Number') }}</p>
                            <p class="font-mono text-base">{{ $vendor->account_number }}</p>
                        </div>
                    @endif

                    @if ($vendor->notes)
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __('Notes') }}</p>
                            <p class="text-base whitespace-pre-wrap">{{ $vendor->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Services --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-700">
                            {{ __('Services') }}
                            <span
                                class="ml-1 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $vendor->services->count() }}</span>
                        </h3>
                        <a href="{{ route('services.create', ['vendor_uuid' => $vendor->uuid]) }}"
                            class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            {{ __('Add Service') }}
                        </a>
                    </div>

                    @if ($vendor->services->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No services linked to this vendor yet.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
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
                                            {{ __('Plan') }}</th>
                                        <th scope="col"
                                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            {{ __('Category') }}</th>
                                        <th scope="col"
                                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            {{ __('Status') }}</th>
                                        <th scope="col" class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($vendor->services as $service)
                                        <tr>
                                            <td class="px-4 py-3 font-mono text-sm">{{ $service->service_code }}</td>
                                            <td class="px-4 py-3 text-sm">{{ $service->name }}</td>
                                            <td class="px-4 py-3 text-sm">{{ $service->plan ?: '—' }}</td>
                                            <td class="px-4 py-3 text-sm">
                                                <span
                                                    class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                                    {{ str_replace('_', ' ', ucfirst($service->category)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                @if ($service->status === 'active')
                                                    <span
                                                        class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                                                @elseif ($service->status === 'paused')
                                                    <span
                                                        class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">{{ __('Paused') }}</span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">{{ __('Cancelled') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm">
                                                <a href="{{ route('services.show', $service) }}"
                                                    class="text-blue-600 hover:text-blue-800">{{ __('View') }}</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Delete --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-semibold text-red-700 mb-2">{{ __('Danger Zone') }}</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('Deleting this vendor is permanent. This will fail if the vendor has active services.') }}
                    </p>
                    <form method="POST" action="{{ route('vendors.destroy', $vendor) }}"
                        onsubmit="return confirm('{{ __('Are you sure you want to delete this vendor?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                            {{ __('Delete Vendor') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
