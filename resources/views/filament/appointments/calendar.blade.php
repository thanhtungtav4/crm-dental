@php($viewState = $this->calendarViewState())

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
                <p class="text-xl font-semibold">{{ number_format($viewState['metrics']['total']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                <p class="text-xs text-amber-700 dark:text-amber-300">Đã đặt</p>
                <p class="text-xl font-semibold text-amber-700 dark:text-amber-300">{{ number_format($viewState['metrics']['scheduled']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-400/30 dark:bg-blue-500/10">
                <p class="text-xs text-blue-700 dark:text-blue-300">Đã xác nhận</p>
                <p class="text-xl font-semibold text-blue-700 dark:text-blue-300">{{ number_format($viewState['metrics']['confirmed']) }}</p>
            </div>
            <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3 dark:border-cyan-400/30 dark:bg-cyan-500/10">
                <p class="text-xs text-cyan-700 dark:text-cyan-300">Đang khám</p>
                <p class="text-xl font-semibold text-cyan-700 dark:text-cyan-300">{{ number_format($viewState['metrics']['in_progress']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                <p class="text-xs text-emerald-700 dark:text-emerald-300">Hoàn thành</p>
                <p class="text-xl font-semibold text-emerald-700 dark:text-emerald-300">{{ number_format($viewState['metrics']['completed']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                <p class="text-xs text-rose-700 dark:text-rose-300">No-show</p>
                <p class="text-xl font-semibold text-rose-700 dark:text-rose-300">{{ number_format($viewState['metrics']['no_show']) }}</p>
            </div>
        </div>

        <div
            x-data="@include('filament.appointments.partials.calendar-shell-state')"
            x-init="init(@js($viewState['status_colors']))"
            x-on:close-modal.window="handleModalClosed($event)"
        >
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
                        @foreach($viewState['branches'] as $branchId => $branchName)
                            <option value="{{ $branchId }}">{{ $branchName }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Bác sĩ</label>
                    <select x-model="filters.doctorId" @change="applyFilters()" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Tất cả</option>
                        @foreach($viewState['doctors'] as $doctorId => $doctorName)
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

            <x-filament::modal
                id="appointment-calendar-reschedule-modal"
                width="2xl"
                heading="Dời lịch hẹn"
                description="Xác nhận thời gian mới và ghi lại lý do thay đổi trước khi cập nhật lịch hẹn."
            >
                <div class="space-y-4">
                    <div class="grid gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-2 dark:border-gray-800 dark:bg-gray-900/70">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Lịch hẹn</p>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="rescheduleDialog.appointmentTitle || 'Lịch hẹn bệnh nhân'"></p>
                        </div>

                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Thời gian mới</p>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="rescheduleDialog.startLabel || 'Chưa chọn'"></p>
                        </div>

                        <div class="space-y-1" x-show="rescheduleDialog.doctorLabel">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Bác sĩ</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200" x-text="rescheduleDialog.doctorLabel"></p>
                        </div>

                        <div class="space-y-1" x-show="rescheduleDialog.branchLabel">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Chi nhánh</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200" x-text="rescheduleDialog.branchLabel"></p>
                        </div>
                    </div>

                    <div
                        x-cloak
                        x-show="rescheduleDialog.conflictMessage"
                        class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-100"
                    >
                        <p class="font-semibold">Khung giờ mới đang bị trùng lịch.</p>
                        <p class="mt-1" x-text="rescheduleDialog.conflictMessage"></p>
                        <p class="mt-2 text-xs text-warning-800/90 dark:text-warning-200/80">
                            Nếu vẫn cần lưu, hệ thống sẽ ghi nhận đây là thao tác override.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label for="calendar-reschedule-reason" class="block text-sm font-medium text-gray-900 dark:text-white">
                            Lý do dời lịch
                        </label>
                        <textarea
                            id="calendar-reschedule-reason"
                            x-model="rescheduleDialog.reason"
                            rows="4"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            placeholder="Ví dụ: bệnh nhân xin đổi giờ, bác sĩ thay đổi lịch, điều phối qua chi nhánh khác..."
                        ></textarea>
                        <p
                            x-cloak
                            x-show="rescheduleDialog.errorMessage"
                            class="text-sm text-danger-600 dark:text-danger-400"
                            x-text="rescheduleDialog.errorMessage"
                        ></p>
                    </div>
                </div>

                <x-slot name="footer">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="crm-btn crm-btn-outline crm-btn-md"
                            x-bind:disabled="rescheduleDialog.isSubmitting"
                            x-on:click="closeRescheduleDialog()"
                        >
                            Hủy
                        </button>

                        <button
                            type="button"
                            class="crm-btn crm-btn-primary crm-btn-md"
                            x-bind:disabled="rescheduleDialog.isSubmitting"
                            x-on:click="submitReschedule()"
                        >
                            <span x-show="!rescheduleDialog.isSubmitting" x-text="rescheduleDialog.force ? 'Override và lưu' : 'Lưu thay đổi'"></span>
                            <span x-cloak x-show="rescheduleDialog.isSubmitting">Đang cập nhật...</span>
                        </button>
                    </div>
                </x-slot>
            </x-filament::modal>
        </div>
    </div>
</x-filament::section>
