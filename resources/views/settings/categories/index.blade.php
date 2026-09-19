<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Categories') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'category-created')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Category added successfully.') }}
                </div>
            @elseif (session('status') === 'category-updated')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Category updated successfully.') }}
                </div>
            @elseif (session('status') === 'category-deleted')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Category deleted.') }}
                </div>
            @elseif (session('status') === 'category-in-use')
                <div class="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    {{ __('This category cannot be deleted because vendors or services are assigned to it.') }}
                </div>
            @endif

            @foreach ([['type' => 'vendor', 'label' => __('Vendor Categories'), 'items' => $vendorCategories], ['type' => 'service', 'label' => __('Service Categories'), 'items' => $serviceCategories]] as $group)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-700">{{ $group['label'] }}</h3>
                            <a href="{{ route('settings.categories.create', ['type' => $group['type']]) }}"
                                class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                {{ __('New Category') }}
                            </a>
                        </div>

                        @if ($group['items']->isEmpty())
                            <p class="text-sm text-gray-500">{{ __('No categories defined yet.') }}</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <caption class="sr-only">{{ $group['label'] }}</caption>
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Name') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                            <th class="px-4 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($group['items'] as $category)
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $category->name }}</td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span class="inline-block rounded px-2 py-0.5 text-xs {{ $category->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                        {{ ucfirst($category->status) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                                    <a href="{{ route('settings.categories.edit', $category) }}"
                                                        class="text-indigo-600 hover:text-indigo-800 mr-3">{{ __('Edit') }}</a>
                                                    <form method="POST" action="{{ route('settings.categories.destroy', $category) }}"
                                                        class="inline"
                                                        onsubmit="return confirm('{{ __('Delete this category?') }}');">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="text-red-500 hover:text-red-700">{{ __('Delete') }}</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
