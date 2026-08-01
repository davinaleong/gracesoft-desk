<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('GitHub Connection') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status') === 'github-connected')
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ __('GitHub account connected successfully.') }}
                </div>
            @elseif (session('status') === 'github-disconnected')
                <div class="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    {{ __('GitHub account disconnected.') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">

                    @if ($connection)
                        <div class="flex items-center gap-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                {{ __('Connected') }}
                            </span>
                        </div>

                        <dl class="divide-y divide-gray-100 text-sm">
                            <div class="flex justify-between py-2">
                                <dt class="font-medium text-gray-500">{{ __('GitHub Login') }}</dt>
                                <dd class="text-gray-900">{{ $connection->github_login }}</dd>
                            </div>
                            <div class="flex justify-between py-2">
                                <dt class="font-medium text-gray-500">{{ __('GitHub ID') }}</dt>
                                <dd class="text-gray-900">{{ $connection->github_id }}</dd>
                            </div>
                            <div class="flex justify-between py-2">
                                <dt class="font-medium text-gray-500">{{ __('Scopes') }}</dt>
                                <dd class="text-gray-900">{{ $connection->token_scope ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between py-2">
                                <dt class="font-medium text-gray-500">{{ __('Connected') }}</dt>
                                <dd class="text-gray-900">{{ $connection->connected_at->toFormattedDateString() }}</dd>
                            </div>
                        </dl>

                        <form method="POST" action="{{ route('settings.github.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500"
                                onclick="return confirm('{{ __('Disconnect your GitHub account?') }}')">
                                {{ __('Disconnect GitHub') }}
                            </button>
                        </form>

                    @else
                        <div class="flex items-center gap-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                {{ __('Not connected') }}
                            </span>
                        </div>

                        <p class="text-sm text-gray-500">
                            {{ __('Connect your GitHub account to enable project repository linking and automatic commit ingestion.') }}
                        </p>

                        <a href="{{ route('settings.github.redirect') }}"
                            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            {{ __('Connect GitHub') }}
                        </a>
                    @endif

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
