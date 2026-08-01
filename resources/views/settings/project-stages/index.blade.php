<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Project Stages') }}
            </h2>

            <a href="{{ route('settings.project-stages.create') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New Stage') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status') === 'project-stage-created')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Stage added successfully.') }}
                </div>
            @elseif (session('status') === 'project-stage-updated')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Stage updated successfully.') }}
                </div>
            @elseif (session('status') === 'project-stage-deleted')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('Stage deleted.') }}
                </div>
            @elseif (session('status') === 'project-stage-in-use')
                <div class="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    {{ __('This stage cannot be deleted because time entries are assigned to it.') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($stages->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No stages defined yet.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <caption class="sr-only">{{ __('Project stages list') }}</caption>
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Order') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Name') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Keywords') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Default') }}</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($stages as $stage)
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-500">
                                                <div class="flex items-center gap-1">
                                                    <span class="w-6 text-center">{{ $stage->sort_order }}</span>
                                                    <div class="flex flex-col gap-0.5">
                                                        @unless ($loop->first)
                                                            <form method="POST" action="{{ route('settings.project-stages.move-up', $stage) }}">
                                                                @csrf @method('PATCH')
                                                                <button type="submit" class="text-gray-400 hover:text-gray-700 leading-none text-xs" title="{{ __('Move up') }}">▲</button>
                                                            </form>
                                                        @endunless
                                                        @unless ($loop->last)
                                                            <form method="POST" action="{{ route('settings.project-stages.move-down', $stage) }}">
                                                                @csrf @method('PATCH')
                                                                <button type="submit" class="text-gray-400 hover:text-gray-700 leading-none text-xs" title="{{ __('Move down') }}">▼</button>
                                                            </form>
                                                        @endunless
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $stage->name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                @if ($stage->keywords)
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach ($stage->keywords as $keyword)
                                                            <span class="inline-block rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700">{{ $keyword }}</span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <span class="inline-block rounded px-2 py-0.5 text-xs {{ $stage->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                    {{ ucfirst($stage->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500">
                                                {{ $stage->is_default ? __('Yes') : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                                <a href="{{ route('settings.project-stages.edit', $stage) }}"
                                                    class="text-indigo-600 hover:text-indigo-800 mr-3">{{ __('Edit') }}</a>
                                                <form method="POST" action="{{ route('settings.project-stages.destroy', $stage) }}"
                                                    class="inline"
                                                    onsubmit="return confirm('{{ __('Delete this stage?') }}');">
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
        </div>
    </div>
</x-app-layout>
