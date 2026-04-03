{
    componentId: @js($this->getId()),
    calendar: null,
    filters: {
        status: '',
        branchId: '',
        doctorId: '',
    },
    rescheduleDialog: {
        modalId: 'appointment-calendar-reschedule-modal',
        appointmentId: null,
        appointmentTitle: '',
        startAtIso: '',
        startLabel: '',
        doctorLabel: '',
        branchLabel: '',
        reason: '',
        force: false,
        conflictMessage: '',
        errorMessage: '',
        isSubmitting: false,
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
                info.revert()
                this.openRescheduleDialog({
                    appointmentId: Number(info.event.id),
                    appointmentTitle: info.event.title,
                    startAtIso: info.event.startStr,
                    doctorLabel: info.event.extendedProps?.doctor ?? '',
                    branchLabel: info.event.extendedProps?.branch ?? '',
                })
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
    async callReschedule(appointmentId, startAtIso, force, reason) {
        const component = window.Livewire?.find(this.componentId)
        if (!component) {
            return { ok: false, message: 'Không thể kết nối tới phiên làm việc.' }
        }

        return await component.call(
            'rescheduleAppointmentFromCalendar',
            Number(appointmentId),
            startAtIso,
            force,
            reason,
        )
    },
    formatDateLabel(startAtIso) {
        try {
            return new Intl.DateTimeFormat('vi-VN', {
                weekday: 'long',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(new Date(startAtIso))
        } catch (error) {
            return startAtIso
        }
    },
    dispatchModal(eventName) {
        window.dispatchEvent(new CustomEvent(eventName, {
            detail: { id: this.rescheduleDialog.modalId },
        }))
    },
    resetRescheduleDialog() {
        this.rescheduleDialog = {
            modalId: 'appointment-calendar-reschedule-modal',
            appointmentId: null,
            appointmentTitle: '',
            startAtIso: '',
            startLabel: '',
            doctorLabel: '',
            branchLabel: '',
            reason: '',
            force: false,
            conflictMessage: '',
            errorMessage: '',
            isSubmitting: false,
        }
    },
    openRescheduleDialog(payload) {
        this.resetRescheduleDialog()
        this.rescheduleDialog = {
            ...this.rescheduleDialog,
            appointmentId: payload.appointmentId ?? null,
            appointmentTitle: payload.appointmentTitle ?? '',
            startAtIso: payload.startAtIso ?? '',
            startLabel: this.formatDateLabel(payload.startAtIso ?? ''),
            doctorLabel: payload.doctorLabel ?? '',
            branchLabel: payload.branchLabel ?? '',
        }

        this.dispatchModal('open-modal')
    },
    closeRescheduleDialog() {
        if (this.rescheduleDialog.isSubmitting) {
            return
        }

        this.resetRescheduleDialog()
        this.dispatchModal('close-modal')
    },
    handleModalClosed(event) {
        if (event.detail?.id !== this.rescheduleDialog.modalId || this.rescheduleDialog.isSubmitting) {
            return
        }

        this.resetRescheduleDialog()
    },
    async submitReschedule() {
        if (this.rescheduleDialog.isSubmitting) {
            return
        }

        const normalizedReason = String(this.rescheduleDialog.reason || '').trim()

        if (!normalizedReason) {
            this.rescheduleDialog.errorMessage = 'Vui lòng nhập lý do dời lịch hẹn.'
            return
        }

        this.rescheduleDialog.isSubmitting = true
        this.rescheduleDialog.errorMessage = ''

        let result

        try {
            result = await this.callReschedule(
                this.rescheduleDialog.appointmentId,
                this.rescheduleDialog.startAtIso,
                this.rescheduleDialog.force,
                normalizedReason,
            )
        } catch (error) {
            this.rescheduleDialog.errorMessage = 'Không thể kết nối tới phiên làm việc.'
            this.rescheduleDialog.isSubmitting = false

            return
        }

        if (result?.ok) {
            this.rescheduleDialog.isSubmitting = false
            this.calendar?.refetchEvents()
            this.closeRescheduleDialog()

            return
        }

        const message = String(result?.message || 'Không thể dời lịch hẹn.')

        if (!this.rescheduleDialog.force && message.includes('trùng lịch')) {
            this.rescheduleDialog.conflictMessage = message
            this.rescheduleDialog.force = true
            this.rescheduleDialog.isSubmitting = false

            return
        }

        this.rescheduleDialog.errorMessage = message
        this.rescheduleDialog.isSubmitting = false
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
