@props([
    'pagePanel',
])

<div class="space-y-4">
    @include('filament.pages.partials.customer-care-overview-panel', [
        'overviewPanel' => $pagePanel['overview_panel'],
    ])

    @include('filament.pages.partials.customer-care-tabs-nav', [
        'tabsPanel' => $pagePanel['tabs_panel'],
    ])

    @include('filament.pages.partials.customer-care-table-panel', [
        'activeTabView' => $pagePanel['active_tab_view'],
    ])
</div>
