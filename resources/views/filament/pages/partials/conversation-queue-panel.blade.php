@props([
    'queuePanel',
])

<x-filament::section :heading="$queuePanel['heading']" :description="$queuePanel['description']">
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($queuePanel['inbox_stat_cards'] as $card)
                @include('filament.pages.partials.conversation-inbox-stat-card', ['card' => $card])
            @endforeach
        </div>

        <div class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50/70 p-3 dark:border-gray-800 dark:bg-gray-950/40">
            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem]">
                <div class="space-y-2">
                    <label for="conversation-search" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ $queuePanel['search_label'] }}
                    </label>
                    <input
                        id="conversation-search"
                        type="search"
                        name="conversation-search"
                        wire:model.live.debounce.400ms="search"
                        autocomplete="off"
                        inputmode="search"
                        placeholder="{{ $queuePanel['search_placeholder'] }}"
                        class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        data-testid="conversation-search"
                    />
                </div>

                <div class="space-y-2">
                    <label for="conversation-provider-filter" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ $queuePanel['provider_label'] }}
                    </label>
                    <select
                        id="conversation-provider-filter"
                        wire:model.live="providerFilter"
                        class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        data-testid="conversation-provider-filter"
                    >
                        @foreach($queuePanel['provider_filter_options'] as $providerValue => $providerLabel)
                            <option value="{{ $providerValue }}">{{ $providerLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($queuePanel['rendered_inbox_tabs'] as $tab)
                    <button
                        type="button"
                        wire:key="conversation-tab-{{ $tab['key'] }}"
                        wire:click="$set('inboxTab', '{{ $tab['key'] }}')"
                        class="{{ $tab['button_class'] }} inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium transition"
                        data-testid="conversation-tab-{{ $tab['key'] }}"
                    >
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $queuePanel['results_text'] }}
            </p>
        </div>

        <div class="space-y-3">
            @forelse($queuePanel['conversation_rows'] as $conversationRow)
                @include('filament.pages.partials.conversation-list-item', [
                    'conversationRow' => $conversationRow,
                ])
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-8 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    {{ $queuePanel['empty_state_text'] }}
                </div>
            @endforelse
        </div>
    </div>
</x-filament::section>
