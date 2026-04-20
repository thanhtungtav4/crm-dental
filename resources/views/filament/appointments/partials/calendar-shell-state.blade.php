@props([
    'panel',
])

{
    componentId: @js($this->getId()),
    calendar: null,
    isFetchingEvents: false,
    pendingDrop: null,
    filters: {
        status: '',
        branchId: '',
        doctorId: '',
    },
    rescheduleDialog: {
        modalId: @js($panel['modal_id']),
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
                const baseUrl = @js($panel['create_url'])
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
            eventDrop: (info) => {
                this.pendingDrop = info
                this.openRescheduleDialog({
                    appointmentId: Number(info.event.id),
                    appointmentTitle: info.event.title,
                    startAtIso: info.event.startStr,
                    doctorLabel: info.event.extendedProps?.doctor ?? '',
                    branchLabel: info.event.extendedProps?.branch ?? '',
                })
            },
            eventDidMount: (info) => {
                const contextLines = this.eventContextLines(info)
                const tooltip = contextLines.join('\n')
                const assistiveLabel = contextLines.join(', ') || info.event.title

                info.el.setAttribute('title', tooltip)
                info.el.setAttribute('aria-label', assistiveLabel)
            },
            events: async (fetchInfo, successCallback, failureCallback) => {
                this.isFetchingEvents = true
                try {
                    const events = await this.callFetchEvents(fetchInfo.startStr, fetchInfo.endStr)
                    successCallback(Array.isArray(events) ? events : [])
                } catch (error) {
                    failureCallback(error)
                } finally {
                    this.isFetchingEvents = false
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
            return { ok: false, message: @js($panel['connection_error_message']) }
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
    eventContextLines(info) {
        const props = info.event.extendedProps || {}
        const startLabel = info.event.start ? this.formatDateLabel(info.event.start.toISOString()) : ''

        return [
            props.patient || info.event.title ? `Bệnh nhân: ${props.patient || info.event.title}` : null,
            startLabel ? `Thời gian: ${startLabel}` : null,
            props.doctor ? `Bác sĩ: ${props.doctor}` : null,
            props.branch ? `Chi nhánh: ${props.branch}` : null,
            props.statusLabel ? `Trạng thái: ${props.statusLabel}` : null,
            props.note ? `Ghi chú: ${props.note}` : null,
        ].filter(Boolean)
    },
    dispatchModal(eventName) {
        window.dispatchEvent(new CustomEvent(eventName, {
            detail: { id: this.rescheduleDialog.modalId },
        }))
    },
    resetRescheduleDialog() {
        this.rescheduleDialog = {
            modalId: @js($panel['modal_id']),
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
        this.$nextTick(() => {
            setTimeout(() => {
                document.getElementById('calendar-reschedule-reason')?.focus()
            }, 150)
        })
    },
    closeRescheduleDialog() {
        if (this.rescheduleDialog.isSubmitting) {
            return
        }

        if (this.pendingDrop) {
            this.pendingDrop.revert()
            this.pendingDrop = null
        }

        this.resetRescheduleDialog()
        this.dispatchModal('close-modal')
    },
    handleModalClosed(event) {
        if (event.detail?.id !== this.rescheduleDialog.modalId || this.rescheduleDialog.isSubmitting) {
            return
        }

        if (this.pendingDrop) {
            this.pendingDrop.revert()
            this.pendingDrop = null
        }

        this.resetRescheduleDialog()
    },
    async submitReschedule() {
        if (this.rescheduleDialog.isSubmitting) {
            return
        }

        const normalizedReason = String(this.rescheduleDialog.reason || '').trim()

        if (!normalizedReason) {
            this.rescheduleDialog.errorMessage = @js($panel['reason_required_message'])
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
            this.rescheduleDialog.errorMessage = @js($panel['connection_error_message'])
            this.rescheduleDialog.isSubmitting = false

            return
        }

        if (result?.ok) {
            this.pendingDrop = null
            this.rescheduleDialog.isSubmitting = false
            this.calendar?.refetchEvents()
            this.closeRescheduleDialog()

            return
        }

        const message = String(result?.message || @js($panel['default_error_message']))

        if (!this.rescheduleDialog.force && message.includes(@js($panel['conflict_keyword']))) {
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
    selectedOptionLabel(selectId, selectedValue, fallback) {
        const select = document.getElementById(selectId)
        const selectedOption = Array.from(select?.options || [])
            .find((option) => option.value === String(selectedValue))

        return selectedOption?.textContent?.trim() || select?.selectedOptions?.[0]?.textContent?.trim() || fallback
    },
    activeFilterSummary() {
        return [
            this.selectedOptionLabel(@js($panel['status_filter_id']), this.filters.status, @js($panel['all_status_label'])),
            this.selectedOptionLabel(@js($panel['branch_filter_id']), this.filters.branchId, @js($panel['all_branch_label'])),
            this.selectedOptionLabel(@js($panel['doctor_filter_id']), this.filters.doctorId, @js($panel['all_doctor_label'])),
        ].join(' · ')
    },
}
