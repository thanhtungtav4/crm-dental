@php
    use App\Models\Appointment;

    // Eager-load relationships to build richer event data
    $appointments = Appointment::query()
        ->with(['patient:id,full_name,phone,email','doctor:id,name,phone,specialty','branch:id,name'])
        ->get();

    $statusColors = [
        Appointment::STATUS_SCHEDULED => ['#3b82f6', '#1d4ed8'],      // blue
        Appointment::STATUS_CONFIRMED => ['#10b981', '#059669'],      // green
        Appointment::STATUS_IN_PROGRESS => ['#f59e0b', '#d97706'],    // amber
        Appointment::STATUS_COMPLETED => ['#6b7280', '#4b5563'],      // gray
        Appointment::STATUS_RESCHEDULED => ['#8b5cf6', '#7c3aed'],    // violet
        Appointment::STATUS_CANCELLED => ['#ef4444', '#b91c1c'],      // red
        Appointment::STATUS_NO_SHOW => ['#9ca3af', '#6b7280'],        // muted gray
    ];

    $events = $appointments->map(function ($a) use ($statusColors) {
        $status = Appointment::normalizeStatus($a->status) ?? Appointment::DEFAULT_STATUS;
        $statusLabel = Appointment::statusLabel($status);
        [$bg, $bd] = $statusColors[$status] ?? $statusColors[Appointment::DEFAULT_STATUS];
        $patient = optional($a->patient);
        $doctor = optional($a->doctor);
        $branch = optional($a->branch);
        $title = ($patient->full_name ?: 'Chưa rõ bệnh nhân');
        return [
            'id' => $a->id,
            'title' => $title,
            'start' => \Carbon\Carbon::parse($a->date)->toIso8601String(),
            'url' => \App\Filament\Resources\Appointments\AppointmentResource::getUrl('edit', ['record' => $a->id]),
            'backgroundColor' => $bg,
            'borderColor' => $bd,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'status' => $status,
                'statusLabel' => $statusLabel,
                'note' => $a->note,
                'patient' => $patient->full_name,
                'doctor' => $doctor->name,
                'branch' => $branch->name,
                'doctorPhone' => $doctor->phone,
                'branch_id' => $a->branch_id,
                'doctor_id' => $a->doctor_id,
            ],
        ];
    });

    $branches = \App\Models\Branch::query()->pluck('name','id');
    $doctors = \App\Models\User::role('Doctor')->pluck('name','id');
@endphp

<x-filament::section>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
            <div class="space-y-1">
                <h1 class="fi-header-heading">Lịch hẹn</h1>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    Google Calendar:
                    @if($this->isGoogleCalendarEnabled())
                        <span class="inline-flex items-center rounded-md bg-success-50 px-2 py-0.5 text-success-700 dark:bg-success-500/20 dark:text-success-300">
                            Đang bật ({{ $this->getGoogleCalendarSyncModeLabel() }})
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                            Đang tắt
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div x-data="calendar()" x-init="init(@js($events), @js($statusColors))">
            <div id="calendar" class="w-full bg-white rounded-lg shadow ring-1 ring-gray-200 crm-calendar-shell"></div>
        </div>
    </div>
</x-filament::section>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
function calendar() {
    return {
        calendar: null,
        allEvents: [],
        filters: {
            status: '',
            branchId: '',
            doctorId: '',
        },
        init(events, statusColors) {
            this.allEvents = events
            const el = document.getElementById('calendar')
            this.calendar = new FullCalendar.Calendar(el, {
                locale: 'vi',
                initialView: 'dayGridMonth',
                height: 'auto',
                expandRows: true,
                nowIndicator: true,
                firstDay: 1,
                buttonText: {
                    today: 'Hôm nay',
                    month: 'Tháng',
                    week: 'Tuần',
                    day: 'Ngày',
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
                navLinks: true,
                selectable: true,
                selectMirror: true,
                dateClick: (info) => {
                    // Quick-create by redirecting to create with date param
                    const url = @json(\App\Filament\Resources\Appointments\AppointmentResource::getUrl('create')) + '?date=' + encodeURIComponent(info.dateStr)
                    window.location.href = url
                },
                eventClick: (info) => {
                    if (info.event.url) {
                        info.jsEvent.preventDefault()
                        window.location.href = info.event.url
                    }
                },
                eventDidMount: (info) => {
                    const p = info.event.extendedProps || {}
                    const tip = [
                        p.patient ? `BN: ${p.patient}` : null,
                        p.doctor ? `BS: ${p.doctor}` : null,
                        p.branch ? `CN: ${p.branch}` : null,
                        p.statusLabel ? `TT: ${p.statusLabel}` : null,
                        p.note ? `Ghi chú: ${p.note}` : null,
                    ].filter(Boolean).join('\n')
                    info.el.setAttribute('title', tip)
                },
                events: events,
            })
            this.calendar.render()

            // Watch filters
            this.$watch('filters', () => { this.applyFilters() }, { deep: true })
        },
        applyFilters() {
            const filtered = this.allEvents.filter(e => {
                const p = e.extendedProps || {}
                if (this.filters.status && p.status !== this.filters.status) return false
                if (this.filters.branchId && String(p.branch_id || '') !== String(this.filters.branchId)) return false
                if (this.filters.doctorId && String(p.doctor_id || '') !== String(this.filters.doctorId)) return false
                return true
            })
            this.calendar.removeAllEvents()
            this.calendar.addEventSource(filtered)
        },
    }
}
</script>
@endpush
