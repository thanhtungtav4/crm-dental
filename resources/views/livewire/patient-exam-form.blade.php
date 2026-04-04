<div
    class="space-y-4 crm-exam"
    x-data="{ recentlySaved: false, saveTimer: null, markSaved() { this.recentlySaved = true; clearTimeout(this.saveTimer); this.saveTimer = setTimeout(() => { this.recentlySaved = false }, 1800) } }"
    x-on:saved.window="markSaved()"
>
    <div class="crm-exam-header-card">
        <div class="crm-exam-header">
            <div class="crm-exam-header-copy">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="crm-section-title">Khám &amp; Điều trị</h3>
                    @if($examSession)
                        <span class="crm-session-badge is-active">Đang mở: {{ $examSession->session_date?->format('d/m/Y') ?? '-' }}</span>
                    @endif
                    @if($examSession?->is_locked)
                        <span class="crm-session-badge is-locked">Phiếu đã khóa</span>
                    @endif
                </div>
                <p class="crm-exam-header-description">
                    Phiếu khám tự động lưu sau mỗi thay đổi. Chọn ngày khám trước khi mở phiếu mới để tránh tạo nhầm phiên làm việc.
                </p>
            </div>

            <div class="crm-exam-create-bar">
                <label class="crm-exam-date-field">
                    <span class="crm-exam-date-label">Ngày khám mới</span>
                    <input
                        type="date"
                        wire:model="newSessionDate"
                        class="crm-exam-date-input"
                    >
                </label>

                <button
                    type="button"
                    wire:click="createSession"
                    wire:loading.attr="disabled"
                    wire:target="createSession"
                    class="crm-btn crm-btn-primary crm-btn-md"
                >
                    <span wire:loading.remove wire:target="createSession">Thêm phiếu khám</span>
                    <span wire:loading.inline-flex wire:target="createSession" class="items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-90" d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-linecap="round" stroke-width="3"></path>
                        </svg>
                        Đang tạo...
                    </span>
                </button>

                <div class="crm-exam-status-stack">
                    <div wire:loading.delay.flex class="crm-exam-status-pill is-loading">
                        Đang đồng bộ dữ liệu...
                    </div>
                    <div x-show="recentlySaved" x-cloak class="crm-exam-status-pill is-saved">
                        Đã lưu tự động
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(empty($sessionCards))
        <div class="rounded-md border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Chưa có phiếu khám cho bệnh nhân này. Kế hoạch điều trị, nếu có, sẽ hiển thị ở phần bên dưới.
        </div>
    @else
        <div class="crm-section-card">
            @foreach($sessionCards as $sessionCard)
                <div class="crm-exam-session" wire:key="exam-session-{{ $sessionCard['id'] }}">
                    <div class="crm-session-header">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="setActiveSession({{ $sessionCard['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="setActiveSession"
                                class="crm-session-toggle"
                            >
                                <span class="crm-session-caret {{ $sessionCard['is_active'] ? 'is-open' : '' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </span>
                                NGÀY KHÁM: {{ $sessionCard['date_label'] }}
                            </button>
                            @if($sessionCard['is_active'])
                                <span class="crm-session-badge is-active">Đang xem</span>
                            @endif
                            @if($sessionCard['is_locked'])
                                <span class="crm-session-badge is-locked">Đã khóa tiến trình</span>
                            @endif
                            <button
                                type="button"
                                wire:click="startEditingSession({{ $sessionCard['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="startEditingSession"
                                @disabled($sessionCard['is_locked'])
                                class="crm-btn crm-btn-outline crm-btn-icon text-gray-500 disabled:opacity-50"
                                title="{{ $sessionCard['is_locked'] ? 'Ngày khám đã có tiến trình điều trị nên không thể chỉnh sửa.' : 'Sửa ngày khám' }}"
                                aria-label="{{ $sessionCard['is_locked'] ? 'Ngày khám đã khóa, không thể chỉnh sửa' : 'Sửa ngày khám' }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 20h9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16.5 3.5a2.12 2.12 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                </svg>
                            </button>
                        </div>

                        <div class="crm-session-actions">
                            @if(filled($medicalRecordActionUrl) && filled($medicalRecordActionLabel))
                                <a
                                    href="{{ $medicalRecordActionUrl }}"
                                    target="_blank"
                                    class="crm-btn crm-btn-primary crm-btn-sm"
                                >
                                    {{ $medicalRecordActionLabel }}
                                </a>
                            @endif

                            <button
                                type="button"
                                wire:click="deleteSession({{ $sessionCard['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="deleteSession"
                                @disabled($sessionCard['is_locked'])
                                class="crm-btn crm-btn-outline crm-btn-icon text-gray-600 disabled:opacity-50"
                                title="{{ $sessionCard['is_locked'] ? 'Ngày khám đã có tiến trình điều trị nên không thể xóa được.' : 'Xóa phiếu khám' }}"
                                aria-label="{{ $sessionCard['is_locked'] ? 'Ngày khám đã khóa, không thể xóa' : 'Xóa phiếu khám' }}"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 4v6m4-6v6m4-10v12a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    @if($sessionCard['is_active'])
                        @if($editingSessionId === $sessionCard['id'])
                            <div class="crm-session-edit-bar">
                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Sửa ngày khám:</span>
                                <input
                                    type="date"
                                    wire:model="editingSessionDate"
                                    class="crm-exam-date-input crm-exam-date-input-sm"
                                >
                                <button
                                    type="button"
                                    wire:click="saveEditingSession"
                                    wire:loading.attr="disabled"
                                    wire:target="saveEditingSession"
                                    class="crm-btn crm-btn-primary crm-btn-sm"
                                >
                                    <span wire:loading.remove wire:target="saveEditingSession">Lưu</span>
                                    <span wire:loading.inline wire:target="saveEditingSession">Đang lưu...</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="cancelEditingSession"
                                    wire:loading.attr="disabled"
                                    wire:target="saveEditingSession"
                                    class="crm-btn crm-btn-outline crm-btn-sm"
                                >
                                    Hủy
                                </button>
                                <span class="crm-session-inline-hint">Ngày khám đã có tiến trình điều trị sẽ không thể đổi lịch.</span>
                            </div>
                        @endif

                        <div
                            class="px-4 pb-4"
                            x-data="@include('livewire.partials.patient-exam.workspace-state')"
                            x-init="initOtherDiagnosis()"
                        >
                            @include('livewire.partials.patient-exam.general-section')

                            @include('livewire.partials.patient-exam.indications-section')

                            @include('livewire.partials.patient-exam.diagnosis-section')
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
