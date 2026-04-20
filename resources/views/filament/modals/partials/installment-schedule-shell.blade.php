@props(['viewState'])

<div class="space-y-4">
    @if($viewState['has_schedule'])
        @include('filament.modals.partials.installment-schedule-table', ['viewState' => $viewState])
        @include('filament.modals.partials.installment-schedule-summary-cards', ['summaryCards' => $viewState['summary_cards']])
    @else
        @include('filament.modals.partials.installment-schedule-empty-state', ['emptyState' => $viewState['empty_state']])
    @endif
</div>
