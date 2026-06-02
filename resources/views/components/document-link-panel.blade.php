@props(['panelId', 'documentableType', 'documentableUuid', 'redirectBack', 'documents'])

<div id="{{ $panelId }}" class="hidden mb-6 border border-gray-200 rounded-md p-4 bg-gray-50" x-data="{
    search: '',
    selectedUuid: '',
    selectedName: '',
    open: false,
    docs: {{ Js::from($documents->map(fn($d) => ['uuid' => $d->uuid, 'name' => $d->name, 'size' => $d->formattedSize()])->values()) }},
    get results() {
        if (!this.search.trim()) return [];
        const q = this.search.toLowerCase();
        return this.docs.filter(d => d.name.toLowerCase().includes(q)).slice(0, 8);
    },
    select(doc) {
        this.selectedUuid = doc.uuid;
        this.selectedName = doc.name;
        this.open = false;
        this.search = '';
    },
    clear() {
        this.selectedUuid = '';
        this.selectedName = '';
    },
}">
    <form method="POST" class="space-y-3" :action="selectedUuid ? '/documents/' + selectedUuid + '/attach' : ''"
        @submit.prevent="selectedUuid && $el.submit()">
        @csrf
        <input type="hidden" name="documentable_type" value="{{ $documentableType }}">
        <input type="hidden" name="documentable_uuid" value="{{ $documentableUuid }}">
        <input type="hidden" name="redirect_back" value="{{ $redirectBack }}">

        <div>
            <x-input-label :value="__('Search Documents')" />

            {{-- Selected chip --}}
            <div x-show="selectedUuid" style="display:none"
                class="mt-1 flex items-center justify-between gap-2 px-3 py-2 bg-white border border-indigo-300 rounded-md">
                <span class="text-sm font-medium truncate" x-text="selectedName"></span>
                <button type="button" @click="clear()"
                    class="shrink-0 text-xs text-gray-400 hover:text-red-500 font-medium">{{ __('Clear') }}</button>
            </div>

            {{-- Search input + dropdown --}}
            <div x-show="!selectedUuid" class="relative mt-1">
                <x-text-input x-model="search" @focus="open = true" @input="open = true"
                    @keydown.escape="open = false; search = ''" @blur="open = false" type="text" class="block w-full"
                    placeholder="{{ __('Type to search\u2026') }}" />

                <div x-show="open && results.length > 0"
                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                    <template x-for="doc in results" :key="doc.uuid">
                        <button type="button" @mousedown.prevent="select(doc)"
                            class="w-full text-left flex items-center justify-between gap-2 px-3 py-2 text-sm hover:bg-indigo-50 border-b border-gray-100 last:border-0">
                            <span x-text="doc.name" class="font-medium truncate"></span>
                            <span x-text="doc.size" class="shrink-0 text-xs text-gray-400"></span>
                        </button>
                    </template>
                </div>

                <p x-show="open && search.trim() && results.length === 0" class="mt-1 text-xs text-gray-400">
                    {{ __('No documents found.') }}</p>
            </div>
        </div>

        <div class="flex justify-end">
            <x-primary-button x-bind:disabled="!selectedUuid">
                {{ __('Link') }}
            </x-primary-button>
        </div>
    </form>
</div>
