<button
    type="button"
    wire:key="conversation-{{ $conversationRow['id'] }}"
    wire:click="selectConversation({{ $conversationRow['id'] }})"
    class="{{ $conversationRow['button_class'] }} flex w-full flex-col gap-3 rounded-2xl border px-4 py-4 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $conversationRow['display_name'] }}
                </p>

                <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $conversationRow['provider_badge_class'] }}">
                    {{ $conversationRow['provider_label'] }}
                </span>

                <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $conversationRow['handoff_status_badge_class'] }}">
                    {{ $conversationRow['handoff_status_label'] }}
                </span>

                <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $conversationRow['handoff_priority_badge_class'] }}">
                    {{ $conversationRow['handoff_priority_label'] }}
                </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $conversationRow['branch_label'] }}
            </p>
        </div>

        @if($conversationRow['unread_count'] > 0)
            <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-danger-500 px-2 py-1 text-[11px] font-semibold text-white">
                {{ $conversationRow['unread_count'] }}
            </span>
        @endif
    </div>

    <p class="line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
        {{ $conversationRow['preview'] }}
    </p>

    @if($conversationRow['handoff_summary_preview'])
        <p class="line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
            Note nội bộ: {{ $conversationRow['handoff_summary_preview'] }}
        </p>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 text-[11px] text-gray-500 dark:text-gray-400">
        <span>{{ $conversationRow['lead_status_label'] }}</span>

        <div class="flex flex-wrap items-center gap-2">
            @if($conversationRow['next_action_label'])
                <span class="rounded-full border border-gray-200 bg-white px-2 py-1 dark:border-gray-700 dark:bg-gray-900">
                    Follow-up {{ $conversationRow['next_action_label'] }}
                </span>
            @endif

            <span>{{ $conversationRow['last_message_at_human'] }}</span>
        </div>
    </div>
</button>
