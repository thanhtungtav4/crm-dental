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

        <div
            class="relative"
            role="region"
            aria-label="{{ $viewState['shell_panel']['calendar_region_label'] }}"
            x-bind:aria-busy="isFetchingEvents.toString()"
        >
            <div
                x-cloak
                x-show="isFetchingEvents"
                class="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/75 backdrop-blur-sm dark:bg-gray-950/75"
                role="status"
                aria-live="polite"
                aria-label="{{ $viewState['shell_panel']['calendar_loading_label'] }}"
            >
                <svg class="h-8 w-8 animate-spin text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="sr-only">{{ $viewState['shell_panel']['calendar_loading_label'] }}</span>
            </div>
            <div id="calendar" class="crm-calendar-shell w-full rounded-lg bg-white shadow ring-1 ring-gray-200"></div>
        </div>

        @include('filament.appointments.partials.calendar-reschedule-modal', [
            'panel' => $viewState['reschedule_modal_panel'],
        ])
    </div>
</div>
