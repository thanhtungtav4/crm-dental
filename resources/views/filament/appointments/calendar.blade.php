@php
    use App\Models\User;
    use App\Support\BranchAccess;

    $metrics = $this->getOperationalStatusMetrics();
    $statusColors = $this->getStatusColors();

    $authUser = auth()->user();
    $branchIds = [];

    if ($authUser instanceof User && ! $authUser->hasRole('Admin')) {
        $branchIds = BranchAccess::accessibleBranchIds($authUser);
    }

    $branches = \App\Models\Branch::query()
        ->when(
            $authUser instanceof User && ! $authUser->hasRole('Admin'),
            fn ($query) => $branchIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $branchIds),
        )
        ->orderBy('name')
        ->pluck('name', 'id');

    $doctors = User::query()
        ->role('Doctor')
        ->when(
            $authUser instanceof User && ! $authUser->hasRole('Admin'),
            function ($query) use ($branchIds) {
                if ($branchIds === []) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->where(function ($doctorQuery) use ($branchIds) {
                    $doctorQuery
                        ->whereIn('branch_id', $branchIds)
                        ->orWhereHas('activeDoctorBranchAssignments', fn ($assignmentQuery) => $assignmentQuery->whereIn('branch_id', $branchIds));
                });
            }
        )
        ->orderBy('name')
        ->pluck('name', 'id');
@endphp

<x-filament::section>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
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

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60">
                <p class="text-xs text-gray-500">Tổng lịch tuần</p>
                <p class="text-xl font-semibold">{{ number_format($metrics['total']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                <p class="text-xs text-amber-700 dark:text-amber-300">Đã đặt</p>
                <p class="text-xl font-semibold text-amber-700 dark:text-amber-300">{{ number_format($metrics['scheduled']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-400/30 dark:bg-blue-500/10">
                <p class="text-xs text-blue-700 dark:text-blue-300">Đã xác nhận</p>
                <p class="text-xl font-semibold text-blue-700 dark:text-blue-300">{{ number_format($metrics['confirmed']) }}</p>
            </div>
            <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3 dark:border-cyan-400/30 dark:bg-cyan-500/10">
                <p class="text-xs text-cyan-700 dark:text-cyan-300">Đang khám</p>
                <p class="text-xl font-semibold text-cyan-700 dark:text-cyan-300">{{ number_format($metrics['in_progress']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                <p class="text-xs text-emerald-700 dark:text-emerald-300">Hoàn thành</p>
                <p class="text-xl font-semibold text-emerald-700 dark:text-emerald-300">{{ number_format($metrics['completed']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                <p class="text-xs text-rose-700 dark:text-rose-300">No-show</p>
                <p class="text-xl font-semibold text-rose-700 dark:text-rose-300">{{ number_format($metrics['no_show']) }}</p>
            </div>
        </div>

        <div x-data="calendar()" x-init="init(@js($statusColors))">
            <div class="mb-4 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 md:grid-cols-4 dark:border-gray-700 dark:bg-gray-900/60">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Trạng thái</label>
                    <select x-model="filters.status" @change="applyFilters()" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Tất cả</option>
                        <option value="{{ \App\Models\Appointment::STATUS_SCHEDULED }}">Đã đặt</option>
                        <option value="{{ \App\Models\Appointment::STATUS_CONFIRMED }}">Đã xác nhận</option>
                        <option value="{{ \App\Models\Appointment::STATUS_IN_PROGRESS }}">Đang khám</option>
                        <option value="{{ \App\Models\Appointment::STATUS_COMPLETED }}">Hoàn thành</option>
                        <option value="{{ \App\Models\Appointment::STATUS_NO_SHOW }}">No-show</option>
                        <option value="{{ \App\Models\Appointment::STATUS_CANCELLED }}">Đã hủy</option>
                        <option value="{{ \App\Models\Appointment::STATUS_RESCHEDULED }}">Đã hẹn lại</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Chi nhánh</label>
                    <select x-model="filters.branchId" @change="applyFilters()" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Tất cả</option>
                        @foreach($branches as $branchId => $branchName)
                            <option value="{{ $branchId }}">{{ $branchName }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Bác sĩ</label>
                    <select x-model="filters.doctorId" @change="applyFilters()" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Tất cả</option>
                        @foreach($doctors as $doctorId => $doctorName)
                            <option value="{{ $doctorId }}">{{ $doctorName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="button" @click="resetFilters()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        Đặt lại bộ lọc
                    </button>
                </div>
            </div>

            <div id="calendar" class="crm-calendar-shell w-full rounded-lg bg-white shadow ring-1 ring-gray-200"></div>
        </div>
    </div>
</x-filament::section>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
function calendar() {
    return {
        componentId: @js($this->getId()),
        calendar: null,
        filters: {
            status: '',
            branchId: '',
            doctorId: '',
        },
        init(statusColors) {
            const el = document.getElementById('calendar')
            this.calendar = new FullCalendar.Calendar(el, {
                locale: 'vi',
                initialView: 'timeGridWeek',
                height: 'auto',
                expandRows: true,
                nowIndicator: true,
                firstDay: 1,
                buttonText: {
                    today: 'Hôm nay',
                    week: 'Tuần',
                    day: 'Ngày',
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,timeGridDay',
                },
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
                navLinks: true,
                selectable: true,
                selectMirror: true,
                editable: true,
                eventDurationEditable: false,
                dateClick: (info) => {
                    const baseUrl = @json(\App\Filament\Resources\Appointments\AppointmentResource::getUrl('create'))
                    const params = new URLSearchParams()
                    params.set('date', info.dateStr)
                    if (this.filters.branchId) params.set('branch_id', this.filters.branchId)
                    if (this.filters.doctorId) params.set('doctor_id', this.filters.doctorId)
                    window.location.href = `${baseUrl}?${params.toString()}`
                },
                eventClick: (info) => {
                    if (info.event.url) {
                        info.jsEvent.preventDefault()
                        window.location.href = info.event.url
                    }
                },
                eventDrop: async (info) => {
                    const reason = window.prompt('Nhap ly do doi lich hen:', '')

                    if (!reason || !reason.trim()) {
                        window.alert('Vui long nhap ly do doi lich hen.')
                        info.revert()
                        return
                    }

                    const normalizedReason = reason.trim()
                    const result = await this.callReschedule(info.event, false, normalizedReason)

                    if (result?.ok) {
                        this.calendar.refetchEvents()
                        return
                    }

                    const conflictMessage = String(result?.message || '')
                    if (conflictMessage.includes('trùng lịch') && window.confirm(`${conflictMessage}\n\nBạn có muốn override để lưu lịch?`)) {
                        const forcedResult = await this.callReschedule(info.event, true, normalizedReason)
                        if (forcedResult?.ok) {
                            this.calendar.refetchEvents()
                            return
                        }

                        window.alert(forcedResult?.message || 'Không thể dời lịch hẹn.')
                        info.revert()
                        return
                    }

                    window.alert(result?.message || 'Không thể dời lịch hẹn.')
                    info.revert()
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
                events: async (fetchInfo, successCallback, failureCallback) => {
                    try {
                        const events = await this.callFetchEvents(fetchInfo.startStr, fetchInfo.endStr)
                        successCallback(Array.isArray(events) ? events : [])
                    } catch (error) {
                        failureCallback(error)
                    }
                },
            })
            this.calendar.render()
        },
        async callFetchEvents(startAtIso, endAtIso) {
            const component = window.Livewire?.find(this.componentId)
            if (!component) {
                return []
            }

            return await component.call('getCalendarEvents', startAtIso, endAtIso, this.filters)
        },
        async callReschedule(event, force, reason) {
            const component = window.Livewire?.find(this.componentId)
            if (!component) {
                return { ok: false, message: 'Không thể kết nối tới phiên làm việc.' }
            }

            return await component.call(
                'rescheduleAppointmentFromCalendar',
                Number(event.id),
                event.startStr,
                force,
                reason,
            )
        },
        applyFilters() {
            if (!this.calendar) {
                return
            }

            this.calendar.refetchEvents()
        },
        resetFilters() {
            this.filters = { status: '', branchId: '', doctorId: '' }
            this.applyFilters()
        },
    }
}
</script>
@endpush
