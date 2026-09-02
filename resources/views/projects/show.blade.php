<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Project Detail') }}
            </h2>

            <a href="{{ route('projects.edit', $project) }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Edit Project') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'project-created')
                <div
                    class="flex items-center justify-between rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <span>{{ __('Project saved. Would you like to create another one?') }}</span>
                    <a href="{{ route('projects.create') }}"
                        class="ml-4 font-semibold underline hover:text-blue-600">{{ __('Create Another') }}</a>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Project Code') }}</p>
                        <p class="font-mono text-base">{{ $project->code }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Name') }}</p>
                        <p class="text-base">{{ $project->name }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Status') }}</p>
                        <p class="text-base">{{ $project->status }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Billable') }}</p>
                        <p class="text-base">{{ $project->is_billable ? __('Yes') : __('No') }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Hourly Rate') }}</p>
                        <p class="text-base">@deskMoney((float) ($project->hourly_rate ?? 0))</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">{{ __('Description') }}</p>
                        <p class="text-base">{{ $project->description ?: __('No description.') }}</p>
                    </div>
                </div>
            </div>

            {{-- GitHub Repository --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" x-data="repoPicker()">
                <div class="p-6 text-gray-900">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('GitHub Repository') }}</h3>

                    @if ($project->github_repo)
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">{{ __('Linked Repository') }}</p>
                                <a href="https://github.com/{{ $project->github_repo }}" target="_blank" rel="noopener noreferrer"
                                    class="text-indigo-600 hover:underline font-mono text-sm">{{ $project->github_repo }}</a>
                                @if ($project->github_branch)
                                    <span class="ml-2 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-mono text-gray-600">{{ $project->github_branch }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-4">
                                <a href="{{ route('projects.pending-commits.index', $project) }}"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 font-semibold">{{ __('Review Commits') }}</a>
                                <form method="POST" action="{{ route('projects.github.destroy', $project) }}"
                                    onsubmit="return confirm('{{ __('Remove the GitHub repository link and delete the webhook?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-sm text-red-600 hover:text-red-800 font-semibold">{{ __('Unlink') }}</button>
                                </form>
                            </div>
                        </div>
                    @elseif (auth()->user()->githubConnection)
                        <div>
                            <button type="button" @click="loadRepos"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 mb-3">
                                {{ __('Link Repository') }}
                            </button>

                            <div x-show="loading" class="text-sm text-gray-500">{{ __('Loading repositories…') }}</div>

                            <div x-show="repos.length > 0 && !loading">
                                <form method="POST" action="{{ route('projects.github.store', $project) }}" class="flex items-start gap-3">
                                    @csrf
                                    <div class="flex-1">
                                        <input type="text" list="repo-list" name="github_repo" x-model="selected"
                                            @input="onRepoInput"
                                            placeholder="{{ __('Search repositories…') }}"
                                            class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                            required>
                                        <datalist id="repo-list">
                                            <template x-for="repo in repos" :key="repo.full_name">
                                                <option :value="repo.full_name"></option>
                                            </template>
                                        </datalist>
                                    </div>
                                    <div class="w-48">
                                        <select name="github_branch" x-model="selectedBranch"
                                            :disabled="branches.length === 0 || branchLoading"
                                            class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm disabled:bg-gray-50 disabled:text-gray-400"
                                            required>
                                            <option value="" x-text="branchLoading ? '{{ __('Loading branches…') }}' : '{{ __('Select a branch…') }}'"></option>
                                            <template x-for="branch in branches" :key="branch">
                                                <option :value="branch" x-text="branch"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <x-primary-button>{{ __('Link') }}</x-primary-button>
                                </form>
                            </div>

                            <div x-show="error" class="text-sm text-red-600" x-text="error"></div>
                            <div x-show="branchError" class="text-sm text-red-600" x-text="branchError"></div>

                            @error('github_repo')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            @error('github_branch')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            {{ __('Connect') }}
                            <a href="{{ route('settings.github.show') }}" class="text-indigo-600 hover:underline">{{ __('GitHub') }}</a>
                            {{ __('in Settings to link a repository.') }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- Documents --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-700">{{ __('Documents') }}</h3>
                        <div class="flex items-center gap-2">
                            @if ($unlinkedDocuments->isNotEmpty())
                                <button type="button"
                                    onclick="document.getElementById('proj-link-panel').classList.toggle('hidden')"
                                    class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                    {{ __('Link Existing') }}
                                </button>
                            @endif
                            <button type="button"
                                onclick="document.getElementById('proj-upload-panel').classList.toggle('hidden')"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                {{ __('Attach Document') }}
                            </button>
                        </div>
                    </div>

                    <x-document-link-panel panel-id="proj-link-panel" documentable-type="project" :documentable-uuid="$project->uuid"
                        redirect-back="project" :documents="$unlinkedDocuments" />

                    <div id="proj-upload-panel"
                        class="{{ $errors->has('file') || $errors->has('name') ? '' : 'hidden' }} mb-6 border border-gray-200 rounded-md p-4 bg-gray-50">
                        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data"
                            class="space-y-3">
                            @csrf
                            <input type="hidden" name="documentable_type" value="project">
                            <input type="hidden" name="documentable_uuid" value="{{ $project->uuid }}">
                            <input type="hidden" name="redirect_back" value="project">

                            <div>
                                <x-input-label for="proj-file" :value="__('File')" />
                                <input id="proj-file" name="file" type="file" required
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp,.gif"
                                    class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md cursor-pointer focus:outline-none focus:ring focus:ring-indigo-300">
                                <x-input-error :messages="$errors->get('file')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="proj-doc-name" :value="__('Display Name (optional)')" />
                                <x-text-input id="proj-doc-name" name="name" type="text" class="mt-1 block w-full"
                                    :value="old('name')" placeholder="{{ __('Leave blank to use original filename') }}" />
                            </div>

                            <div class="flex justify-end">
                                <x-primary-button>{{ __('Upload') }}</x-primary-button>
                            </div>
                        </form>
                    </div>

                    @forelse ($project->documents as $document)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div>
                                <span class="text-sm font-medium">{{ $document->name }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ $document->formattedSize() }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <a href="{{ route('documents.preview', $document) }}" target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-blue-600 hover:text-blue-800">{{ __('Preview') }}</a>
                                <a href="{{ route('documents.download', $document) }}"
                                    class="text-indigo-600 hover:text-indigo-800">{{ __('Download') }}</a>
                                <a href="{{ route('documents.edit', $document) }}"
                                    class="text-gray-600 hover:text-gray-900">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('documents.destroy', $document) }}"
                                    class="inline"
                                    onsubmit="return confirm('{{ __('Delete this document? This cannot be undone.') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_back" value="project">
                                    <button type="submit"
                                        class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('No documents attached.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function repoPicker() {
            return {
                repos: [],
                selected: '',
                loading: false,
                error: '',
                branches: [],
                selectedBranch: '',
                branchLoading: false,
                branchError: '',
                async loadRepos() {
                    this.loading = true;
                    this.error = '';
                    try {
                        const res = await fetch('{{ route('settings.github.repos') }}', {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (!res.ok) throw new Error('Failed to load repositories.');
                        this.repos = await res.json();
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.loading = false;
                    }
                },
                onRepoInput() {
                    const repo = this.repos.find(r => r.full_name === this.selected);
                    this.branches = [];
                    this.selectedBranch = '';
                    this.branchError = '';

                    if (repo) {
                        this.loadBranches(repo.full_name, repo.default_branch);
                    }
                },
                async loadBranches(fullName, defaultBranch) {
                    this.branchLoading = true;
                    this.branchError = '';
                    try {
                        const res = await fetch('{{ route('settings.github.branches') }}?repo=' + encodeURIComponent(fullName), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (!res.ok) throw new Error('Failed to load branches.');
                        this.branches = await res.json();
                        this.selectedBranch = this.branches.includes(defaultBranch)
                            ? defaultBranch
                            : (this.branches[0] ?? '');
                    } catch (e) {
                        this.branchError = e.message;
                    } finally {
                        this.branchLoading = false;
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
