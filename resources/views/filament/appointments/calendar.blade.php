<x-filament::section>
    @include('filament.appointments.partials.calendar-page-shell', [
        'viewState' => $this->calendarViewState(),
    ])
</x-filament::section>
