@props([
    'viewState',
    'showLeadModal' => false,
])

@php
    $pagePanel = $viewState['page_panel'];
@endphp

@if (! $viewState['is_schema_ready'])
    @include('filament.pages.partials.conversation-inbox-schema-notice', [
        'schemaNotice' => $viewState['schema_notice'],
    ])
@else
    @include('filament.pages.partials.conversation-inbox-ready-shell', [
        'viewState' => $viewState,
        'pagePanel' => $pagePanel,
    ])
@endif

@if($showLeadModal && $pagePanel['detail_panel']['conversation'])
    @include('filament.pages.partials.conversation-lead-modal', [
        'leadModalView' => $pagePanel['lead_modal_view'],
    ])
@endif
