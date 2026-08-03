<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Pending Commits') }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $project->code }} — {{ $project->name }}</p>
            </div>
            <a href="{{ route('projects.show', $project) }}"
                class="text-sm text-gray-600 hover:text-gray-900">{{ __('← Back to Project') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <caption class="sr-only">{{ __('Pending commits awaiting review') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Date') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Branch') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Author') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Message') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Diff') }}</th>
                                    <th scope="col" class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($commits as $commit)
                                    <tr>
                                        <td class="px-4 py-3 text-sm whitespace-nowrap">
                                            {{ $commit->committed_at?->toDateString() ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm font-mono">{{ $commit->branch }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $commit->author_name }}</td>
                                        <td class="px-4 py-3 text-sm max-w-xs truncate" title="{{ $commit->message }}">
                                            {{ Str::limit($commit->message, 80) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm whitespace-nowrap text-gray-500">
                                            @if ($commit->additions !== null || $commit->deletions !== null)
                                                <span class="text-green-600">+{{ $commit->additions ?? 0 }}</span>
                                                /
                                                <span class="text-red-500">-{{ $commit->deletions ?? 0 }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <a href="{{ route('projects.pending-commits.create', [$project, $commit]) }}"
                                                class="text-indigo-600 hover:text-indigo-800">{{ __('Convert') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-sm text-gray-500" colspan="6">
                                            {{ __('No pending commits. All caught up!') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $commits->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
