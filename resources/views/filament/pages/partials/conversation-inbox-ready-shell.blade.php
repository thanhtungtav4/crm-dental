@props([
    'viewState',
    'pagePanel',
])

<div wire:poll.{{ $viewState['polling_interval_seconds'] }}s="refreshInbox" class="space-y-6">
    <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
        @include('filament.pages.partials.conversation-queue-panel', [
            'queuePanel' => $pagePanel['queue_panel'],
        ])

        @include('filament.pages.partials.conversation-detail-panel', [
            'detailPanel' => $pagePanel['detail_panel'],
        ])
    </div>
</div>
