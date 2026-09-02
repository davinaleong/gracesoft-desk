<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Service Detail') }}
            </h2>

            <a href="{{ route('services.edit', $service) }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Edit Service') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'service-created')
                <div
                    class="flex items-center justify-between rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <span>{{ __('Service saved. Would you like to create another one?') }}</span>
                    <a href="{{ route('services.create') }}"
                        class="ml-4 font-semibold underline hover:text-blue-600">{{ __('Create Another') }}</a>
                </div>
            @endif

            @if (session('status') === 'service-updated')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Service updated successfully.') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Service Code') }}</p>
                        <p class="font-mono text-base">{{ $service->service_code }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Name') }}</p>
                        <p class="text-base font-semibold">{{ $service->name }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Vendor') }}</p>
                        <p class="text-base">
                            <a href="{{ route('vendors.show', $service->vendor) }}"
                                class="text-blue-600 hover:text-blue-800">{{ $service->vendor->name }}</a>
                            <span
                                class="ml-2 font-mono text-xs text-gray-400">{{ $service->vendor->vendor_code }}</span>
                        </p>
                    </div>

                    @if ($service->plan)
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __('Plan / Tier') }}</p>
                            <p class="text-base">{{ $service->plan }}</p>
                        </div>
                    @endif

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Category') }}</p>
                        <p class="text-base">
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                {{ $service->category?->name ?? '—' }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Status') }}</p>
                        <p class="text-base">
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
                        </p>
                    </div>

                    @if ($service->notes)
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __('Notes') }}</p>
                            <p class="text-base whitespace-pre-wrap">{{ $service->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Delete --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-semibold text-red-700 mb-2">{{ __('Danger Zone') }}</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('Deleting this service is permanent and cannot be undone.') }}
                    </p>
                    <form method="POST" action="{{ route('services.destroy', $service) }}"
                        onsubmit="return confirm('{{ __('Are you sure you want to delete this service?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                            {{ __('Delete Service') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
