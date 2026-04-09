@props([
    'viewState',
])

<div class="flex flex-col gap-4">
    @include('filament.appointments.partials.calendar-header-panel', [
        'heading' => $viewState['heading'],
        'panel' => $viewState['google_calendar_panel'],
    ])

    @include('filament.appointments.partials.calendar-metric-cards', [
        'cards' => $viewState['metric_cards'],
    ])

    <div
        x-data="@include('filament.appointments.partials.calendar-shell-state', ['panel' => $viewState['shell_panel']])"
        x-init="init(@js($viewState['status_colors']))"
        x-on:close-modal.window="handleModalClosed($event)"
    >
        @include('filament.appointments.partials.calendar-filters-panel', [
            'panel' => $viewState['filters_panel'],
        ])

        <div id="calendar" class="crm-calendar-shell w-full rounded-lg bg-white shadow ring-1 ring-gray-200"></div>

        @include('filament.appointments.partials.calendar-reschedule-modal', [
            'panel' => $viewState['reschedule_modal_panel'],
        ])
    </div>
</div>
