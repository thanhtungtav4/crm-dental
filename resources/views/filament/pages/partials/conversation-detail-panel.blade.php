@props([
    'detailPanel',
])

<x-filament::section :heading="$detailPanel['heading']" :description="$detailPanel['description']">
    @if($detailPanel['conversation'])
        <div class="flex min-h-[72vh] flex-col gap-4">
            @include('filament.pages.partials.conversation-selected-header', [
                'conversation' => $detailPanel['conversation'],
                'summaryCards' => $detailPanel['selected_conversation_view']['summary_cards'],
                'assigneeOptions' => $detailPanel['selected_conversation_view']['assignee_options'],
                'customerEditUrl' => $detailPanel['selected_conversation_view']['customer_edit_url'],
            ])

            @include('filament.pages.partials.conversation-handoff-form', [
                'conversation' => $detailPanel['conversation'],
                'handoffPanel' => $detailPanel['selected_conversation_view']['handoff_panel'],
            ])

            @include('filament.pages.partials.conversation-thread-panel', [
                'threadPanel' => $detailPanel['selected_conversation_view']['thread_panel'],
                'composerPanel' => $detailPanel['selected_conversation_view']['composer_panel'],
                'messages' => $detailPanel['selected_conversation_view']['messages'],
            ])
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-16 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            {{ $detailPanel['empty_state_text'] }}
        </div>
    @endif
</x-filament::section>
