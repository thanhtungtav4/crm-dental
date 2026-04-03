<x-filament-panels::page>
    @include('filament.pages.partials.conversation-inbox-page-shell', [
        'viewState' => $this->inboxViewState,
        'showLeadModal' => $showLeadModal,
    ])
</x-filament-panels::page>
