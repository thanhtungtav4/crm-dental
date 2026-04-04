<x-filament-panels::page>
    @include('filament.pages.partials.conversation-inbox-page-shell', [
        'viewState' => $this->inboxViewState,
        'pagePanel' => $this->inboxViewState['page_panel'],
        'showLeadModal' => $showLeadModal,
    ])
</x-filament-panels::page>
