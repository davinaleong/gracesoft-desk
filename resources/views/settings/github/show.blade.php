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

            @forelse ($connections as $connection)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 space-y-4">
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
                                <dt class="font-medium text-gray-500">{{ __('Linked projects') }}</dt>
                                <dd class="text-gray-900">{{ $connection->projects_count }}</dd>
                            </div>
                            <div class="flex justify-between py-2">
                                <dt class="font-medium text-gray-500">{{ __('Connected') }}</dt>
                                <dd class="text-gray-900">{{ $connection->connected_at->toFormattedDateString() }}</dd>
                            </div>
                        </dl>

                        <form method="POST" action="{{ route('settings.github.destroy', $connection->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500"
                                onclick="return confirm('{{ __('Disconnect this GitHub account?') }}')">
                                {{ __('Disconnect GitHub') }}
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center gap-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                {{ __('Not connected') }}
                            </span>
                        </div>

                        <p class="text-sm text-gray-500">
                            {{ __('Connect your GitHub account to enable project repository linking and automatic commit ingestion.') }}
                        </p>
                    </div>
                </div>
            @endforelse

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-3">
                    <a href="{{ route('settings.github.redirect') }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        {{ $connections->isEmpty() ? __('Connect GitHub') : __('Connect another GitHub account') }}
                    </a>
                    @if ($connections->isNotEmpty())
                        <p class="text-xs text-gray-500">
                            {{ __('GitHub authorizes the account you are currently signed in to on github.com. Switch accounts there first, then connect.') }}
                        </p>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
