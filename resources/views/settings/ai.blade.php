<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('AI & Privacy') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'ai-settings-updated')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ __('AI settings saved.') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-700 space-y-2">
                <p class="font-semibold">{{ __('What leaves this server') }}</p>
                <p>{{ __('Only commit messages, branch names, the number of changed files and your stage names and keywords — plus file paths if you allow them below. Diffs, author names and author emails are never sent. Emails and token-like strings are masked as [redacted] first. Stage keyword matches never call AI.') }}</p>
            </div>

            <form method="POST" action="{{ route('settings.ai.update') }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4"
                x-data="{ provider: @js(old('ai_provider', $settings->provider() ?? '')) }">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-2">
                    <input type="hidden" name="ai_enabled" value="0">
                    <input id="ai_enabled" name="ai_enabled" type="checkbox" value="1" @checked(old('ai_enabled', $settings->enabled()))
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <x-input-label for="ai_enabled" :value="__('Enable AI commit summaries')" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="ai_provider" :value="__('Provider')" />
                        <select id="ai_provider" name="ai_provider" x-model="provider"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('None') }}</option>
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="openai_compatible">{{ __('OpenAI-compatible (local / self-hosted)') }}</option>
                        </select>
                        <x-input-error :messages="$errors->get('ai_provider')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="ai_model" :value="__('Model')" />
                        <x-text-input id="ai_model" name="ai_model" type="text" class="mt-1 block w-full" :value="old('ai_model', $settings->model())" />
                        <x-input-error :messages="$errors->get('ai_model')" class="mt-2" />
                    </div>
                </div>

                <div x-show="provider === 'openai_compatible'">
                    <x-input-label for="ai_base_url" :value="__('Base URL (e.g. http://localhost:11434/v1)')" />
                    <x-text-input id="ai_base_url" name="ai_base_url" type="url" class="mt-1 block w-full" :value="old('ai_base_url', $settings->baseUrl())" />
                    <x-input-error :messages="$errors->get('ai_base_url')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="ai_api_key" :value="__('API Key')" />
                    <input id="ai_api_key" name="ai_api_key" type="password" autocomplete="off" value=""
                        placeholder="{{ $settings->hasStoredApiKey() ? __('A key is saved — leave blank to keep it') : __('Paste an API key') }}"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Stored encrypted. It is never shown again.') }}</p>
                    @if ($settings->hasStoredApiKey())
                        <label class="mt-2 flex items-center gap-2 text-sm text-red-700">
                            <input type="checkbox" name="clear_api_key" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            {{ __('Remove the saved key') }}
                        </label>
                    @endif
                    <x-input-error :messages="$errors->get('ai_api_key')" class="mt-2" />
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="ai_send_file_paths" value="0">
                    <input id="ai_send_file_paths" name="ai_send_file_paths" type="checkbox" value="1" @checked(old('ai_send_file_paths', $settings->sendFilePaths()))
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <x-input-label for="ai_send_file_paths" :value="__('Also send changed file paths')" />
                </div>

                <div class="max-w-xs">
                    <x-input-label for="ai_log_retention_days" :value="__('Keep the AI request log for (days)')" />
                    <x-text-input id="ai_log_retention_days" name="ai_log_retention_days" type="number" min="1" max="3650" class="mt-1 block w-full"
                        :value="old('ai_log_retention_days', $settings->retentionDays())" required />
                    <x-input-error :messages="$errors->get('ai_log_retention_days')" class="mt-2" />
                </div>

                <x-primary-button>{{ __('Save AI Settings') }}</x-primary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Recent AI Requests') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <caption class="sr-only">{{ __('AI request log') }}</caption>
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase">
                                <th scope="col" class="px-2 py-2 text-left">{{ __('Time') }}</th>
                                <th scope="col" class="px-2 py-2 text-left">{{ __('Provider / Model') }}</th>
                                <th scope="col" class="px-2 py-2 text-left">{{ __('Purpose') }}</th>
                                <th scope="col" class="px-2 py-2 text-left">{{ __('Payload') }}</th>
                                <th scope="col" class="px-2 py-2 text-left">{{ __('Outcome') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($recentRequests as $aiRequest)
                                <tr>
                                    <td class="px-2 py-2">{{ $aiRequest->requested_at?->format('d M Y H:i') }}</td>
                                    <td class="px-2 py-2">{{ $aiRequest->provider }} / {{ $aiRequest->model }}</td>
                                    <td class="px-2 py-2">{{ $aiRequest->purpose }}{{ $aiRequest->project ? ' · '.$aiRequest->project->code : '' }}</td>
                                    <td class="px-2 py-2 font-mono text-xs" title="{{ $aiRequest->payload_hash }}">{{ substr($aiRequest->payload_hash, 0, 12) }}… · {{ $aiRequest->payload_length }} B</td>
                                    <td class="px-2 py-2">{{ $aiRequest->outcome }}{{ $aiRequest->error ? ': '.$aiRequest->error : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-2 py-4 text-gray-500">{{ __('No AI requests yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
