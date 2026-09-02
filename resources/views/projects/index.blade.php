<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Projects') }}
            </h2>

            <div class="flex items-center gap-2">
                <a href="{{ route('projects.import.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Import CSV') }}
                </a>
                <a href="{{ route('projects.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New Project') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if (session('status') === 'project-created')
                        <p class="mb-4 text-sm text-green-600">{{ __('Project created successfully.') }}</p>
                    @endif

                    @if (session('status') === 'project-updated')
                        <p class="mb-4 text-sm text-green-600">{{ __('Project updated successfully.') }}</p>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <caption class="sr-only">{{ __('Projects list') }}</caption>
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
                                        {{ __('Status') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('Billable') }}</th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ __('GitHub') }}</th>
                                    <th scope="col" class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($projects as $project)
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm">{{ $project->code }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $project->name }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $project->status }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            {{ $project->is_billable ? __('Yes') : __('No') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if ($project->github_repo)
                                                <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs bg-green-100 text-green-700"
                                                    title="{{ $project->github_repo }}{{ $project->github_branch ? '@'.$project->github_branch : '' }}">
                                                    {{ __('Linked') }}
                                                </span>
                                                <span class="ml-1 font-mono text-xs text-gray-500">{{ Str::limit($project->github_repo, 24) }}</span>
                                            @else
                                                <span class="inline-block rounded px-2 py-0.5 text-xs bg-gray-100 text-gray-500">
                                                    {{ __('Not linked') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <a href="{{ route('projects.show', $project) }}"
                                                aria-label="{{ __('View project :code', ['code' => $project->code]) }}"
                                                class="text-blue-600 hover:text-blue-800">{{ __('View') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-sm text-gray-500" colspan="6">
                                            {{ __('No projects yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $projects->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
