<div class="space-y-4 crm-exam">
    <div class="crm-exam-header">
        <h3 class="crm-section-title">Khám &amp; Điều trị</h3>
        <button
            type="button"
            wire:click="createSession"
            class="crm-btn crm-btn-primary h-10 px-4 text-sm"
        >
            Thêm phiếu khám
        </button>
    </div>

    @if($sessions->isEmpty())
        <div class="rounded-md border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Chưa có phiếu khám cho bệnh nhân này.
        </div>
    @else
        <div class="crm-section-card">
            @foreach($sessions as $session)
                @php
                    $sessionDate = $session->date?->format('d/m/Y') ?? '-';
                    $isActive = $activeSessionId === $session->id;
                    $isLocked = (bool) $session->is_locked;
                    $adultUpper = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
                    $childUpper = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
                    $childLower = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];
                    $adultLower = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
                @endphp

                <div class="crm-exam-session" wire:key="exam-session-{{ $session->id }}">
                    <div class="crm-session-header">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="setActiveSession({{ $session->id }})"
                                class="crm-session-toggle"
                            >
                                <span class="crm-session-caret {{ $isActive ? 'is-open' : '' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </span>
                                NGÀY KHÁM: {{ $sessionDate }}
                            </button>
                            <button
                                type="button"
                                wire:click="startEditingSession({{ $session->id }})"
                                @disabled($isLocked)
                                class="crm-btn crm-btn-outline h-7 w-7 p-0 text-gray-500 disabled:opacity-50"
                                title="{{ $isLocked ? 'Ngày khám đã có tiến trình điều trị nên không thể chỉnh sửa.' : 'Sửa ngày khám' }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 20h9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16.5 3.5a2.12 2.12 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                href="{{ route('filament.admin.resources.patient-medical-records.create', ['patient_id' => $patient->id]) }}"
                                target="_blank"
                                class="crm-btn crm-btn-primary h-8 px-3 text-xs"
                            >
                                Tạo bệnh án điện tử
                            </a>

                            <button
                                type="button"
                                wire:click="deleteSession({{ $session->id }})"
                                @disabled($isLocked)
                                class="crm-btn crm-btn-outline h-8 w-8 p-0 text-gray-600 disabled:opacity-50"
                                title="{{ $isLocked ? 'Ngày khám đã có tiến trình điều trị nên không thể xóa được.' : 'Xóa phiếu khám' }}"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 4v6m4-6v6m4-10v12a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    @if($isActive)
                        @if($editingSessionId === $session->id)
                            <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-white px-4 py-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900">
                                <span class="text-xs font-semibold text-gray-600">Sửa ngày khám:</span>
                                <input
                                    type="date"
                                    wire:model="editingSessionDate"
                                    class="h-8 rounded-md border-gray-300 bg-white text-xs focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                >
                                <button
                                    type="button"
                                    wire:click="saveEditingSession"
                                    class="crm-btn crm-btn-primary h-8 px-3 text-xs"
                                >
                                    Lưu
                                </button>
                                <button
                                    type="button"
                                    wire:click="cancelEditingSession"
                                    class="crm-btn crm-btn-outline h-8 px-3 text-xs"
                                >
                                    Hủy
                                </button>
                            </div>
                        @endif

                        <div
                            class="px-4 pb-4"
                            x-data="{
                                openSection: 'general',
                                state: $wire.entangle('tooth_diagnosis_data'),
                                selectedTeeth: [],
                                modalOpen: false,
                                modalNotes: '',
                                draftConditions: [],
                                conditions: @js($conditionsJson ?? []),
                                otherDiagnosisValue: @entangle('other_diagnosis'),
                                otherDiagnosisTags: [],
                                otherDiagnosisInput: '',
                                otherDiagnosisOptions: @js($otherDiagnosisOptions ?? []),
                                otherDiagnosisOpen: false,
                                conditionOrder: @js($conditionOrder ?? []),
                                toothTreatmentStates: @js($toothTreatmentStates),

                                initOtherDiagnosis() {
                                    if (this.otherDiagnosisValue) {
                                        this.otherDiagnosisTags = this.otherDiagnosisValue
                                            .split(',')
                                            .map(value => value.trim())
                                            .filter(Boolean);
                                    }
                                },

                                syncOtherDiagnosis() {
                                    this.otherDiagnosisValue = this.otherDiagnosisTags.join(', ');
                                },

                                addOtherDiagnosisTag(tag) {
                                    const normalized = (tag || '').trim();
                                    if (!normalized) return;
                                    if (!this.otherDiagnosisTags.includes(normalized)) {
                                        this.otherDiagnosisTags.push(normalized);
                                        this.syncOtherDiagnosis();
                                    }
                                },

                                removeOtherDiagnosisTag(index) {
                                    if (index < 0 || index >= this.otherDiagnosisTags.length) return;
                                    this.otherDiagnosisTags.splice(index, 1);
                                    this.syncOtherDiagnosis();
                                },

                                handleOtherDiagnosisKeydown(event) {
                                    if (event.key === 'Enter' || event.key === ',' ) {
                                        event.preventDefault();
                                        this.addOtherDiagnosisTag(this.otherDiagnosisInput);
                                        this.otherDiagnosisInput = '';
                                    }
                                },

                                commitOtherDiagnosisInput() {
                                    if (!this.otherDiagnosisInput) return;
                                    this.addOtherDiagnosisTag(this.otherDiagnosisInput);
                                    this.otherDiagnosisInput = '';
                                },

                                filteredOtherDiagnosisOptions() {
                                    const query = (this.otherDiagnosisInput || '').trim().toLowerCase();
                                    let options = Array.isArray(this.otherDiagnosisOptions) ? this.otherDiagnosisOptions : [];

                                    if (query) {
                                        options = options.filter((option) => {
                                            const label = String(option.label || '').toLowerCase();
                                            const code = String(option.code || '').toLowerCase();
                                            return label.includes(query) || code.includes(query);
                                        });
                                    }

                                    return options.slice(0, 120);
                                },

                                groupedOtherDiagnosisOptions() {
                                    const grouped = {};
                                    this.filteredOtherDiagnosisOptions().forEach((option) => {
                                        const group = option.group || 'Khác';
                                        if (!grouped[group]) grouped[group] = [];
                                        grouped[group].push(option);
                                    });
                                    return Object.entries(grouped).map(([group, items]) => ({ group, items }));
                                },

                                selectOtherDiagnosisOption(option) {
                                    if (!option) return;
                                    this.addOtherDiagnosisTag(option.label || option.code || '');
                                    this.otherDiagnosisInput = '';
                                    this.otherDiagnosisOpen = false;
                                },

                                getToothData(tooth) {
                                    return this.state?.[tooth] || { conditions: [], status: 'current', notes: '' };
                                },

                                ensureToothState(tooth) {
                                    if (!this.state) this.state = {};
                                    if (!this.state[tooth]) this.state[tooth] = { conditions: [], status: 'current', notes: '' };
                                    if (!Array.isArray(this.state[tooth].conditions)) this.state[tooth].conditions = [];
                                },

                                toggleTooth(tooth, event) {
                                    const multiSelect = event && (event.ctrlKey || event.metaKey);
                                    if (!multiSelect) {
                                        this.selectedTeeth = [tooth];
                                        this.openModal();
                                        return;
                                    }

                                    if (this.selectedTeeth.includes(tooth)) {
                                        this.selectedTeeth = this.selectedTeeth.filter((item) => item !== tooth);
                                    } else {
                                        this.selectedTeeth = [...this.selectedTeeth, tooth];
                                    }
                                },

                                isToothSelected(tooth) {
                                    return this.selectedTeeth.includes(tooth);
                                },

                                hasToothSign(tooth) {
                                    const data = this.getToothData(tooth);
                                    return Array.isArray(data.conditions) && data.conditions.length > 0;
                                },

                                clearSelection() {
                                    this.selectedTeeth = [];
                                },

                                getToothTreatmentState(tooth) {
                                    const lookupKey = String(tooth);
                                    const mappedState = this.toothTreatmentStates?.[lookupKey];
                                    if (mappedState) return mappedState;

                                    const data = this.getToothData(tooth);
                                    if (Array.isArray(data.conditions) && data.conditions.length > 0) return 'current';

                                    return 'normal';
                                },

                                getToothTreatmentStateLabel(tooth) {
                                    const state = this.getToothTreatmentState(tooth);
                                    switch (state) {
                                        case 'in_treatment':
                                            return 'Đang được điều trị';
                                        case 'completed':
                                            return 'Hoàn thành điều trị';
                                        case 'current':
                                            return 'Tình trạng hiện tại';
                                        default:
                                            return 'Bình thường';
                                    }
                                },

                                getToothBoxClass(tooth, isChild = false) {
                                    const state = this.getToothTreatmentState(tooth);
                                    let classes = 'crm-tooth-box';
                                    if (isChild) classes += ' crm-tooth-box--child';

                                    if (state === 'in_treatment') {
                                        classes += ' is-in-treatment';
                                    } else if (state === 'completed') {
                                        classes += ' is-completed';
                                    } else if (state === 'current') {
                                        classes += ' is-current';
                                    }

                                    if (this.isToothSelected(tooth)) {
                                        classes += ' is-selected';
                                    }

                                    return classes;
                                },

                                getConditionSortIndex(code) {
                                    const normalized = String(code || '').toUpperCase();
                                    const index = this.conditionOrder.findIndex(
                                        (item) => String(item || '').toUpperCase() === normalized
                                    );
                                    return index === -1 ? 9999 : index;
                                },

                                sortConditionCodes(codes) {
                                    if (!Array.isArray(codes)) return [];
                                    return [...codes].sort((a, b) => this.getConditionSortIndex(a) - this.getConditionSortIndex(b));
                                },

                                displayConditionCode(code) {
                                    const normalized = String(code || '').toUpperCase();
                                    if (normalized === 'KHAC' || normalized === '*') return '*';
                                    const condition = Array.isArray(this.conditions)
                                        ? this.conditions.find(item => String(item.code || '').toUpperCase() === normalized)
                                        : null;
                                    const displayCode = condition?.display_code;
                                    if (displayCode) {
                                        return String(displayCode).replace(/\s+/g, '');
                                    }
                                    return code;
                                },

                                getConditionLabels(tooth) {
                                    let data = this.getToothData(tooth);
                                    if (!data.conditions || data.conditions.length === 0) return '';

                                    return this.sortConditionCodes(data.conditions)
                                        .map((code) => this.displayConditionCode(code))
                                        .join('');
                                },

                                getConditionsList(tooth) {
                                    let data = this.getToothData(tooth);
                                    if (!data.conditions || data.conditions.length === 0) return 'Bình thường';

                                    return this.sortConditionCodes(data.conditions).map(code => {
                                        const normalized = String(code || '').toUpperCase();
                                        let c = this.conditions.find(item => String(item.code || '').toUpperCase() === normalized);
                                        return c ? c.name : code;
                                    }).join(', ');
                                },

                                assignFilesToInput(type, files) {
                                    const input = this.$refs[`indicationInput_${type}`];
                                    if (!input || !files || !files.length) return;
                                    const dataTransfer = new DataTransfer();
                                    Array.from(files).forEach((file) => {
                                        if (file) dataTransfer.items.add(file);
                                    });
                                    input.files = dataTransfer.files;
                                    input.dispatchEvent(new Event('change', { bubbles: true }));
                                },

                                handleIndicationPaste(type, event) {
                                    const items = event.clipboardData?.items || [];
                                    const files = [];
                                    for (const item of items) {
                                        if (item.kind === 'file') {
                                            const file = item.getAsFile();
                                            if (file) files.push(file);
                                        }
                                    }
                                    if (files.length) {
                                        event.preventDefault();
                                        this.assignFilesToInput(type, files);
                                    }
                                },

                                handleIndicationDrop(type, event) {
                                    const files = event.dataTransfer?.files;
                                    if (files && files.length) {
                                        event.preventDefault();
                                        this.assignFilesToInput(type, files);
                                    }
                                },

                                openModal() {
                                    if (!this.selectedTeeth.length) return;

                                    const seedTooth = this.selectedTeeth[0];
                                    const seedData = this.getToothData(seedTooth);
                                    this.draftConditions = Array.isArray(seedData.conditions) ? [...seedData.conditions] : [];
                                    this.draftConditions = this.sortConditionCodes(this.draftConditions);

                                    const notes = this.selectedTeeth.map((tooth) => this.getToothData(tooth).notes || '');
                                    const uniqueNotes = [...new Set(notes)];
                                    this.modalNotes = uniqueNotes.length === 1 ? uniqueNotes[0] : '';
                                    this.modalOpen = true;
                                },

                                closeModal() {
                                    this.modalOpen = false;
                                    this.draftConditions = [];
                                    this.modalNotes = '';
                                },

                                saveDiagnosis() {
                                    if (!this.selectedTeeth.length) {
                                        this.closeModal();
                                        return;
                                    }

                                    this.selectedTeeth.forEach((tooth) => {
                                        this.ensureToothState(tooth);
                                        this.state[tooth].conditions = this.sortConditionCodes(this.draftConditions);
                                        this.state[tooth].notes = this.modalNotes;
                                        this.state[tooth].status = this.draftConditions.length ? 'current' : 'normal';
                                    });

                                    this.closeModal();
                                },

                                toggleCondition(code) {
                                    let index = this.draftConditions.indexOf(code);
                                    if (index === -1) {
                                        this.draftConditions.push(code);
                                    } else {
                                        this.draftConditions.splice(index, 1);
                                    }
                                    this.draftConditions = this.sortConditionCodes(this.draftConditions);
                                },

                                hasCondition(code) {
                                    return this.draftConditions.includes(code);
                                }
                            }"
                            x-init="initOtherDiagnosis()"
                        >
                            <div class="border-b border-gray-200">
                                <button type="button" @click="openSection = openSection === 'general' ? '' : 'general'" class="crm-exam-section-header">
                                    <svg class="h-4 w-4 transition" :class="openSection === 'general' ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    KHÁM TỔNG QUÁT
                                </button>

                                <div x-show="openSection === 'general'" style="display: none;" class="crm-exam-section-body">
                                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                        <div>
                                            <div class="mb-3 flex items-center gap-3">
                                                <label class="min-w-[110px] text-sm font-medium text-gray-700 dark:text-gray-300">Bác sĩ khám</label>
                                                <div class="relative flex-1" x-data="{ open: @entangle('showExaminingDoctorDropdown') }">
                                                    <input
                                                        type="text"
                                                        wire:model.live="examiningDoctorSearch"
                                                        x-on:focus="open = true"
                                                        @click.away="open = false"
                                                        placeholder="Chọn bác sĩ..."
                                                        class="block w-full rounded-md border-gray-300 bg-white text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                                    >
                                                    @if($examining_doctor_id)
                                                        <button type="button" wire:click="clearExaminingDoctor" class="absolute right-2 top-2 text-gray-400 hover:text-red-500">
                                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                        </button>
                                                    @endif

                                                    <div x-show="open" style="display: none;" class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-600 dark:bg-gray-800">
                                                        @forelse($examiningDoctors as $doctor)
                                                            <button type="button" wire:click="selectExaminingDoctor({{ $doctor->id }})" class="block w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700">{{ $doctor->name }}</button>
                                                        @empty
                                                            <div class="px-3 py-2 text-gray-500">Không tìm thấy</div>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>

                                            <textarea
                                                wire:model.live.debounce.500ms="general_exam_notes"
                                                rows="5"
                                                placeholder="Nhập khám tổng quát"
                                                class="crm-textarea text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                            ></textarea>
                                        </div>

                                        <div>
                                            <div class="mb-3 flex items-center gap-3">
                                                <label class="min-w-[110px] text-sm font-medium text-gray-700 dark:text-gray-300">Bác sĩ điều trị</label>
                                                <div class="relative flex-1" x-data="{ open: @entangle('showTreatingDoctorDropdown') }">
                                                    <input
                                                        type="text"
                                                        wire:model.live="treatingDoctorSearch"
                                                        x-on:focus="open = true"
                                                        @click.away="open = false"
                                                        placeholder="Chọn bác sĩ..."
                                                        class="block w-full rounded-md border-gray-300 bg-white text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                                    >
                                                    @if($treating_doctor_id)
                                                        <button type="button" wire:click="clearTreatingDoctor" class="absolute right-2 top-2 text-gray-400 hover:text-red-500">
                                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                        </button>
                                                    @endif

                                                    <div x-show="open" style="display: none;" class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-600 dark:bg-gray-800">
                                                        @forelse($treatingDoctors as $doctor)
                                                            <button type="button" wire:click="selectTreatingDoctor({{ $doctor->id }})" class="block w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700">{{ $doctor->name }}</button>
                                                        @empty
                                                            <div class="px-3 py-2 text-gray-500">Không tìm thấy</div>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>

                                            <textarea
                                                wire:model.live.debounce.500ms="treatment_plan_note"
                                                rows="5"
                                                placeholder="Nhập kế hoạch điều trị"
                                                class="crm-textarea text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-b border-gray-200">
                                <button type="button" @click="openSection = openSection === 'indications' ? '' : 'indications'" class="crm-exam-section-header">
                                    <svg class="h-4 w-4 transition" :class="openSection === 'indications' ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    CHỈ ĐỊNH
                                    <span class="text-xs font-normal italic text-gray-500">(Thêm chỉ định như Chụp X-Quang, Xét nghiệm máu)</span>
                                </button>

                                <div x-show="openSection === 'indications'" style="display: none;" class="crm-exam-section-body">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        @foreach ($indicationTypes as $key => $label)
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                                                    @if (in_array($key, $indications, true)) checked @endif
                                                    wire:click="toggleIndication('{{ $key }}')"
                                                >
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>

                                    @if(in_array('ext', $indications, true))
                                        <div class="mt-4 rounded-md border border-dashed border-gray-300 p-3 dark:border-gray-600">
                                            <div class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Ảnh (ext)</div>
                                            @if (!empty($indicationImages['ext']))
                                                <div class="mb-2 flex flex-wrap gap-2">
                                                    @foreach ($indicationImages['ext'] as $index => $image)
                                                        <div class="relative h-10 w-10 overflow-hidden rounded border border-gray-200">
                                                            <img src="{{ Storage::url($image) }}" class="h-full w-full object-cover">
                                                            <button type="button" wire:click="removeImage('ext', {{ $index }})" class="absolute inset-0 hidden items-center justify-center bg-black/50 text-white hover:flex">×</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <label class="inline-flex cursor-pointer items-center gap-2 rounded border border-dashed border-primary-300 px-3 py-1.5 text-xs text-primary-600 hover:bg-primary-50">
                                                Thêm ảnh hoặc kéo thả
                                                <input type="file" wire:model="tempUploads.ext" multiple class="hidden" x-ref="indicationInput_ext" />
                                            </label>
                                            <div
                                                class="mt-2 rounded-md border border-dashed border-gray-300 bg-white px-3 py-2 text-xs text-gray-500 dark:border-gray-600 dark:bg-gray-900"
                                                @paste.prevent="handleIndicationPaste('ext', $event)"
                                                @drop.prevent="handleIndicationDrop('ext', $event)"
                                                @dragover.prevent
                                            >
                                                Dán ảnh vào đây
                                            </div>
                                        </div>
                                    @endif

                                    @if(in_array('int', $indications, true))
                                        <div class="mt-4 rounded-md border border-dashed border-gray-300 p-3 dark:border-gray-600">
                                            <div class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Ảnh (int)</div>
                                            @if (!empty($indicationImages['int']))
                                                <div class="mb-2 flex flex-wrap gap-2">
                                                    @foreach ($indicationImages['int'] as $index => $image)
                                                        <div class="relative h-10 w-10 overflow-hidden rounded border border-gray-200">
                                                            <img src="{{ Storage::url($image) }}" class="h-full w-full object-cover">
                                                            <button type="button" wire:click="removeImage('int', {{ $index }})" class="absolute inset-0 hidden items-center justify-center bg-black/50 text-white hover:flex">×</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <label class="inline-flex cursor-pointer items-center gap-2 rounded border border-dashed border-primary-300 px-3 py-1.5 text-xs text-primary-600 hover:bg-primary-50">
                                                Thêm ảnh hoặc kéo thả
                                                <input type="file" wire:model="tempUploads.int" multiple class="hidden" x-ref="indicationInput_int" />
                                            </label>
                                            <div
                                                class="mt-2 rounded-md border border-dashed border-gray-300 bg-white px-3 py-2 text-xs text-gray-500 dark:border-gray-600 dark:bg-gray-900"
                                                @paste.prevent="handleIndicationPaste('int', $event)"
                                                @drop.prevent="handleIndicationDrop('int', $event)"
                                                @dragover.prevent
                                            >
                                                Dán ảnh vào đây
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <button type="button" @click="openSection = openSection === 'diagnosis' ? '' : 'diagnosis'" class="crm-exam-section-header">
                                    <svg class="h-4 w-4 transition" :class="openSection === 'diagnosis' ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    CHẨN ĐOÁN VÀ ĐIỀU TRỊ
                                </button>

                                <div x-show="openSection === 'diagnosis'" style="display: none;" class="crm-exam-section-body">
                                    <div class="crm-tooth-chart">
                                        <div class="mb-3 flex flex-wrap items-center justify-end gap-2 text-xs text-gray-600 dark:text-gray-300">
                                            <div class="flex items-center gap-2">
                                                <span class="rounded border border-gray-300 bg-white px-2 py-1 font-semibold">Đã chọn: <span x-text="selectedTeeth.length"></span></span>
                                                <button type="button" @click="clearSelection()" :disabled="selectedTeeth.length === 0" class="crm-btn crm-btn-outline disabled:opacity-50">Bỏ chọn</button>
                                                <button type="button" @click="openModal()" :disabled="selectedTeeth.length === 0" class="crm-btn crm-btn-primary disabled:opacity-50">Chẩn đoán răng đã chọn</button>
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto pb-2">
                                            <div class="text-center">
                                                <div class="crm-tooth-grid">
                                                    @foreach($adultUpper as $t)
                                                        <div class="selection-item" item-key="{{ $t }}" :class="isToothSelected({{ $t }}) ? 'is-selected' : ''" style="text-align: center;">
                                                            <div class="crm-tooth-number mb-1">{{ $t }}</div>
                                                            <button type="button"
                                                                class="tooth-item-cell"
                                                                style="cursor: pointer; border: 0; background: transparent; padding: 0;"
                                                                :title="'Răng ' + {{ $t }} + ': ' + getConditionsList({{ $t }}) + ' | ' + getToothTreatmentStateLabel({{ $t }})"
                                                                @click="toggleTooth({{ $t }}, $event)"
                                                            >
                                                                <div :class="getToothBoxClass({{ $t }})">
                                                                    <span class="tooth-status-list" x-text="getConditionLabels({{ $t }})"></span>
                                                                </div>
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <div class="crm-tooth-grid">
                                                    @foreach($childUpper as $t)
                                                        <div class="selection-item" item-key="{{ $t }}" :class="isToothSelected({{ $t }}) ? 'is-selected' : ''" style="text-align: center;">
                                                            <button type="button"
                                                                class="tooth-item-cell"
                                                                style="cursor: pointer; border: 0; background: transparent; padding: 0;"
                                                                :title="'Răng sữa ' + {{ $t }} + ': ' + getConditionsList({{ $t }}) + ' | ' + getToothTreatmentStateLabel({{ $t }})"
                                                                @click="toggleTooth({{ $t }}, $event)"
                                                            >
                                                                <div :class="getToothBoxClass({{ $t }}, true)"></div>
                                                            </button>
                                                            <div class="crm-tooth-number mt-1">{{ $t }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <div class="crm-tooth-grid">
                                                    @foreach($childLower as $t)
                                                        <div class="selection-item" item-key="{{ $t }}" :class="isToothSelected({{ $t }}) ? 'is-selected' : ''" style="text-align: center;">
                                                            <div class="crm-tooth-number mb-1">{{ $t }}</div>
                                                            <button type="button"
                                                                class="tooth-item-cell"
                                                                style="cursor: pointer; border: 0; background: transparent; padding: 0;"
                                                                :title="'Răng sữa ' + {{ $t }} + ': ' + getConditionsList({{ $t }}) + ' | ' + getToothTreatmentStateLabel({{ $t }})"
                                                                @click="toggleTooth({{ $t }}, $event)"
                                                            >
                                                                <div :class="getToothBoxClass({{ $t }}, true)"></div>
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <div class="crm-tooth-grid">
                                                    @foreach($adultLower as $t)
                                                        <div class="selection-item" item-key="{{ $t }}" :class="isToothSelected({{ $t }}) ? 'is-selected' : ''" style="text-align: center;">
                                                            <button type="button"
                                                                class="tooth-item-cell"
                                                                style="cursor: pointer; border: 0; background: transparent; padding: 0;"
                                                                :title="'Răng ' + {{ $t }} + ': ' + getConditionsList({{ $t }}) + ' | ' + getToothTreatmentStateLabel({{ $t }})"
                                                                @click="toggleTooth({{ $t }}, $event)"
                                                            >
                                                                <div :class="getToothBoxClass({{ $t }})">
                                                                    <span class="tooth-status-list" x-text="getConditionLabels({{ $t }})"></span>
                                                                </div>
                                                            </button>
                                                            <div class="crm-tooth-number mt-1">{{ $t }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        <p class="mt-3 text-center text-xs text-gray-500 italic">
                                            * Bạn có thể chọn 1 tình trạng cho nhiều răng khác nhau bằng cách giữ phím "Ctrl" + chọn "Răng số..." mà bạn muốn tạo thủ thuật điều trị.
                                        </p>

                                        <div class="mt-4 border-t border-gray-300 pt-3">
                                            <div class="flex flex-wrap items-center gap-3">
                                                <label class="min-w-[140px] text-sm font-medium text-gray-700 dark:text-gray-300">(*) Chẩn đoán khác</label>
                                                <div class="relative flex-1" @click.away="otherDiagnosisOpen = false">
                                                    <div class="flex flex-wrap items-center gap-2 rounded-md border border-gray-300 bg-white px-2 py-2 text-sm">
                                                    <template x-for="(tag, index) in otherDiagnosisTags" :key="tag + index">
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-1 text-xs text-primary-700">
                                                            <span x-text="tag"></span>
                                                            <button type="button" @click="removeOtherDiagnosisTag(index)" style="font-size: 12px; color: #6b7280;">×</button>
                                                        </span>
                                                    </template>
                                                    <input
                                                        type="text"
                                                        x-model="otherDiagnosisInput"
                                                        @focus="otherDiagnosisOpen = true"
                                                        @input="otherDiagnosisOpen = true"
                                                        @keydown="handleOtherDiagnosisKeydown($event)"
                                                        @change="commitOtherDiagnosisInput()"
                                                        @blur="commitOtherDiagnosisInput()"
                                                        placeholder="Chọn tình trạng khác"
                                                        class="min-w-[160px] flex-1 border-0 p-0 text-sm focus:ring-0"
                                                    >
                                                </div>

                                                    <div
                                                        x-show="otherDiagnosisOpen"
                                                        x-cloak
                                                        class="absolute z-40 mt-1 w-full rounded-md border border-gray-200 bg-white text-sm shadow-lg"
                                                        style="max-height: 240px; overflow-y: auto;"
                                                    >
                                                        <template x-for="group in groupedOtherDiagnosisOptions()" :key="group.group">
                                                            <div>
                                                                <div class="px-3 py-1 text-xs font-semibold text-gray-500" x-text="group.group"></div>
                                                                <template x-for="option in group.items" :key="option.code">
                                                                    <button
                                                                        type="button"
                                                                        class="block w-full px-3 py-2 text-left hover:bg-gray-100"
                                                                        @click="selectOtherDiagnosisOption(option)"
                                                                    >
                                                                        <span x-text="option.label"></span>
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </template>
                                                        <div x-show="groupedOtherDiagnosisOptions().length === 0" class="px-3 py-2 text-sm text-gray-500">Không có kết quả</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="note-teeth mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-600">
                                        <div class="note-tooth-sign w-full text-gray-500">* chú thích hiện trạng răng</div>
                                        <div class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-gray-500"></span> Tình trạng hiện tại</div>
                                        <div class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-red-500"></span> Đang được điều trị</div>
                                        <div class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-green-500"></span> Hoàn thành điều trị</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                x-show="modalOpen"
                                x-cloak
                                x-transition.opacity
                                class="crm-modal-backdrop"
                                @click.self="closeModal()"
                            >
                                <div class="crm-modal-card" @click.stop>
                                    <div class="crm-modal-header">
                                        <h3 class="text-base font-bold uppercase text-gray-800">
                                            RĂNG <span x-text="selectedTeeth.join(', ')" class="text-primary-600"></span>
                                        </h3>
                                        <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-600">✕</button>
                                    </div>

                                    <div class="crm-modal-body">
                                        <h4 class="mb-3 text-sm font-medium text-gray-700">Chọn tình trạng của răng</h4>
                                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                            @foreach($conditions as $condition)
                                                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-100" :class="hasCondition('{{ $condition->code }}') ? 'bg-primary-50' : ''">
                                                    <input type="checkbox" @click="toggleCondition('{{ $condition->code }}')" :checked="hasCondition('{{ $condition->code }}')" class="h-4 w-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500">
                                                    <span class="text-sm text-gray-700">{{ $condition->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>

                                        <div class="mt-4 border-t border-gray-200 pt-3">
                                            <label class="mb-1 block text-sm font-medium text-gray-700">Ghi chú áp dụng cho các răng đã chọn (nếu có)</label>
                                            <textarea x-model="modalNotes" rows="2" class="w-full rounded-md border-gray-300 bg-white text-sm focus:border-primary-500 focus:ring-primary-500" style="border-radius: 8px; border: 1px solid #d1d5db; padding: 8px 10px;"></textarea>
                                        </div>
                                    </div>

                                    <div class="crm-modal-footer">
                                        <button type="button" @click="saveDiagnosis()" class="crm-btn crm-btn-primary">Chọn</button>
                                        <button type="button" @click="closeModal()" class="crm-btn crm-btn-outline">Hủy bỏ</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
