<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Invoice') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="GET" action="{{ route('invoices.create') }}" class="flex flex-wrap items-end gap-3">
                        <div class="grow">
                            <x-input-label for="client" :value="__('Client')" />
                            <select id="client" name="client" onchange="this.form.submit()"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">{{ __('Select a client') }}</option>
                                @foreach ($clients as $clientOption)
                                    <option value="{{ $clientOption->uuid }}" @selected($client?->uuid === $clientOption->uuid)>{{ $clientOption->client_code }} - {{ $clientOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <noscript>
                            <x-primary-button>{{ __('Load') }}</x-primary-button>
                        </noscript>
                    </form>

                    @if ($clients->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">
                            {{ __('Invoices belong to a client.') }}
                            <a href="{{ route('clients.create') }}" class="text-indigo-600 hover:underline">{{ __('Create a client first.') }}</a>
                        </p>
                    @endif
                </div>
            </div>

            @if ($client)
                <form method="POST" action="{{ route('invoices.store') }}" x-data="{ submitting: false }" @submit="submitting = true"
                    class="space-y-6">
                    @csrf
                    <input type="hidden" name="client_uuid" value="{{ $client->uuid }}">

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 space-y-4" x-data="{ all: false }">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-gray-700">{{ __('Unbilled Time') }}</h3>
                                <fieldset class="flex items-center gap-4 text-sm">
                                    <legend class="sr-only">{{ __('Line grouping') }}</legend>
                                    <label class="flex items-center gap-1">
                                        <input type="radio" name="grouping" value="entry" @checked(old('grouping', 'entry') === 'entry')
                                            class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        {{ __('One line per entry') }}
                                    </label>
                                    <label class="flex items-center gap-1">
                                        <input type="radio" name="grouping" value="stage" @checked(old('grouping') === 'stage')
                                            class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        {{ __('Group by stage') }}
                                    </label>
                                </fieldset>
                            </div>

                            <x-input-error :messages="$errors->get('time_entry_uuids')" class="mt-2" />

                            @if ($entries->isEmpty())
                                <p class="text-sm text-gray-500">{{ __('No billable, unbilled time on this client\'s projects.') }}</p>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="px-3 py-2 text-left">
                                                    <label class="sr-only" for="select-all-entries">{{ __('Select all') }}</label>
                                                    <input id="select-all-entries" type="checkbox" x-model="all"
                                                        @change="$root.querySelectorAll('input[name=\'time_entry_uuids[]\']').forEach(el => el.checked = all)"
                                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                </th>
                                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Date') }}</th>
                                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Project') }}</th>
                                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Stage') }}</th>
                                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Notes') }}</th>
                                                <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Duration') }}</th>
                                                <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($entries as $entry)
                                                <tr>
                                                    <td class="px-3 py-2">
                                                        <label class="sr-only" for="entry-{{ $entry->uuid }}">{{ __('Include entry') }}</label>
                                                        <input id="entry-{{ $entry->uuid }}" type="checkbox" name="time_entry_uuids[]" value="{{ $entry->uuid }}"
                                                            @checked(in_array($entry->uuid, old('time_entry_uuids', []), true))
                                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                    </td>
                                                    <td class="px-3 py-2">@deskDate($entry->entry_date)</td>
                                                    <td class="px-3 py-2">{{ $entry->project?->code }}</td>
                                                    <td class="px-3 py-2">{{ $entry->stage?->name ?? '—' }}</td>
                                                    <td class="px-3 py-2">{{ \Illuminate\Support\Str::limit($entry->notes, 60) }}</td>
                                                    <td class="px-3 py-2 text-right">@deskDuration($entry->duration_minutes)</td>
                                                    <td class="px-3 py-2 text-right">@deskMoney((float) $entry->billable_amount)</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($milestones->isNotEmpty())
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <fieldset class="p-6 text-gray-900 space-y-2">
                                <legend class="text-sm font-semibold text-gray-700">{{ __('Fixed-Fee Milestones') }}</legend>
                                <x-input-error :messages="$errors->get('milestone_uuids')" class="mt-2" />
                                @foreach ($milestones as $milestone)
                                    <label class="flex items-center gap-3 text-sm">
                                        <input type="checkbox" name="milestone_uuids[]" value="{{ $milestone->uuid }}"
                                            @checked(in_array($milestone->uuid, old('milestone_uuids', []), true))
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="font-mono text-xs text-gray-500">{{ $milestone->project?->code }}</span>
                                        <span class="grow">{{ $milestone->name }}{{ $milestone->due_date ? ' · '.__('due').' '.\App\Support\DeskFormat::date($milestone->due_date) : '' }}</span>
                                        <span>@deskMoney((float) $milestone->amount)</span>
                                    </label>
                                @endforeach
                            </fieldset>
                        </div>
                    @endif

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 space-y-4">
                            @include('invoices._manual-lines', ['initialLines' => old('manual_lines', [])])

                            <div>
                                <x-input-label for="notes" :value="__('Notes (printed on the invoice)')" />
                                <textarea id="notes" name="notes" rows="3"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>

                            <div class="flex items-center gap-3">
                                <x-primary-button x-bind:disabled="submitting">{{ __('Create Draft') }}</x-primary-button>
                                <a href="{{ route('invoices.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
