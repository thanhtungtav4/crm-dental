@php
    $conditions = \App\Models\ToothCondition::query()->ordered()->get()->values();

    if (!$conditions->contains(fn(\App\Models\ToothCondition $condition) => strtoupper((string) $condition->code) === 'KHAC')) {
        $conditions->push(new \App\Models\ToothCondition([
            'code' => 'KHAC',
            'name' => '(*) Khác',
            'category' => 'Khác',
            'color' => '#9ca3af',
        ]));
    }

    $conditions = $conditions->values();

    $conditionsJson = $conditions->map(function (\App\Models\ToothCondition $condition) {
        $displayCode = strtoupper((string) $condition->code);

        if (preg_match('/^\(([^)]+)\)/', (string) $condition->name, $matches)) {
            $displayCode = strtoupper(str_replace(' ', '', $matches[1]));
        }

        return [
            'code' => $condition->code,
            'name' => $condition->name,
            'category' => $condition->category,
            'color' => $condition->color,
            'display_code' => $displayCode,
        ];
    })->values()->all();

    $conditionOrder = $conditions
        ->pluck('code')
        ->map(fn($code) => (string) $code)
        ->values()
        ->all();

    $adultUpper = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
    $childUpper = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
    $childLower = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];
    $adultLower = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
    $adultTeeth = array_map('strval', array_merge($adultUpper, $adultLower));
    $childTeeth = array_map('strval', array_merge($childUpper, $childLower));

    $toothDiagnosisStatePath = $getStatePath();
    $dentitionModeStatePath = str_replace('tooth_diagnosis_data', 'tooth_chart_dentition_mode', $toothDiagnosisStatePath);
    $defaultDentitionModeStatePath = str_replace('tooth_diagnosis_data', 'tooth_chart_default_dentition_mode', $toothDiagnosisStatePath);
@endphp

<div
    x-data="{
        state: $wire.entangle('{{ $toothDiagnosisStatePath }}'),
        dentitionMode: $wire.entangle('{{ $dentitionModeStatePath }}'),
        defaultDentitionMode: $wire.entangle('{{ $defaultDentitionModeStatePath }}'),
        adultTeeth: @js($adultTeeth),
        childTeeth: @js($childTeeth),
        selectedTeeth: [],
        modalOpen: false,
        modalNotes: '',
        draftConditions: [],
        conditions: @js($conditionsJson),
        conditionOrder: @js($conditionOrder),
        multiSelectMode: false,

        getToothData(tooth) {
            return this.state?.[tooth] || { conditions: [], status: 'current', notes: '' };
        },

        ensureToothState(tooth) {
            if (!this.state) this.state = {};
            if (!this.state[tooth]) this.state[tooth] = { conditions: [], status: 'current', notes: '' };
            if (!Array.isArray(this.state[tooth].conditions)) this.state[tooth].conditions = [];
        },

        getEffectiveDentitionMode() {
            if (this.dentitionMode === 'adult' || this.dentitionMode === 'child') {
                return this.dentitionMode;
            }

            return this.defaultDentitionMode === 'child' ? 'child' : 'adult';
        },

        showAdultTeeth() {
            return this.getEffectiveDentitionMode() === 'adult';
        },

        showChildTeeth() {
            return this.getEffectiveDentitionMode() === 'child';
        },

        isToothVisible(tooth) {
            const toothKey = String(tooth);
            if (this.showAdultTeeth()) {
                return this.adultTeeth.includes(toothKey);
            }

            return this.childTeeth.includes(toothKey);
        },

        setDentitionMode(mode) {
            if (!['auto', 'adult', 'child'].includes(mode)) return;
            this.dentitionMode = mode;
            this.selectedTeeth = this.selectedTeeth.filter((tooth) => this.isToothVisible(tooth));
            if (!this.selectedTeeth.length) {
                this.closeModal();
            }
        },

        toggleTooth(tooth, event) {
            if (!this.isToothVisible(tooth)) return;

            const multiSelect = this.multiSelectMode || (event && (event.ctrlKey || event.metaKey));
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

        clearSelection() {
            this.selectedTeeth = [];
        },

        toggleMultiSelectMode() {
            this.multiSelectMode = !this.multiSelectMode;
        },

        getToothTreatmentState(tooth) {
            const data = this.getToothData(tooth);
            const status = String(data?.status || '').toLowerCase();

            if (status === 'in_treatment') return 'in_treatment';
            if (status === 'completed') return 'completed';
            if (status === 'current') return 'current';

            if (Array.isArray(data.conditions) && data.conditions.length > 0) {
                return 'current';
            }

            return 'normal';
        },

        getToothTreatmentStateLabel(tooth) {
            const state = this.getToothTreatmentState(tooth);
            switch (state) {
                case 'in_treatment': return 'Đang được điều trị';
                case 'completed': return 'Hoàn thành điều trị';
                case 'current': return 'Tình trạng hiện tại';
                default: return 'Bình thường';
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
            const data = this.getToothData(tooth);
            if (!Array.isArray(data.conditions) || data.conditions.length === 0) return '';

            return this.sortConditionCodes(data.conditions)
                .map((code) => this.displayConditionCode(code))
                .join('');
        },

        getConditionsList(tooth) {
            const data = this.getToothData(tooth);
            if (!Array.isArray(data.conditions) || data.conditions.length === 0) return 'Bình thường';

            return this.sortConditionCodes(data.conditions).map(code => {
                const normalized = String(code || '').toUpperCase();
                const condition = this.conditions.find(item => String(item.code || '').toUpperCase() === normalized);
                return condition ? condition.name : code;
            }).join(', ');
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
            const index = this.draftConditions.indexOf(code);
            if (index === -1) {
                this.draftConditions.push(code);
            } else {
                this.draftConditions.splice(index, 1);
            }

            this.draftConditions = this.sortConditionCodes(this.draftConditions);
        },

        hasCondition(code) {
            return this.draftConditions.includes(code);
        },
    }"
    class="crm-tooth-chart"
>
    <div class="crm-tooth-toolbar mb-3" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px;">
        <div class="crm-dentition-toggle" role="tablist" aria-label="Chế độ sơ đồ răng" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff;">
            <button
                type="button"
                @click="setDentitionMode('auto')"
                class="crm-dentition-option"
                :class="dentitionMode === 'auto' ? 'is-active' : ''"
                :style="dentitionMode === 'auto'
                    ? 'min-width: 78px; height: 30px; padding: 0 10px; border: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; background: var(--crm-primary, #7c6cf6); color: #fff; box-shadow: 0 1px 2px rgba(124, 108, 246, .3);'
                    : 'min-width: 78px; height: 30px; padding: 0 10px; border: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; background: transparent; color: #4b5563; box-shadow: none;'"
                :aria-pressed="dentitionMode === 'auto' ? 'true' : 'false'"
            >
                Tự động
            </button>
            <button
                type="button"
                @click="setDentitionMode('adult')"
                class="crm-dentition-option"
                :class="dentitionMode === 'adult' ? 'is-active' : ''"
                :style="dentitionMode === 'adult'
                    ? 'min-width: 78px; height: 30px; padding: 0 10px; border: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; background: var(--crm-primary, #7c6cf6); color: #fff; box-shadow: 0 1px 2px rgba(124, 108, 246, .3);'
                    : 'min-width: 78px; height: 30px; padding: 0 10px; border: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; background: transparent; color: #4b5563; box-shadow: none;'"
                :aria-pressed="dentitionMode === 'adult' ? 'true' : 'false'"
            >
                Người lớn
            </button>
            <button
                type="button"
                @click="setDentitionMode('child')"
                class="crm-dentition-option"
                :class="dentitionMode === 'child' ? 'is-active' : ''"
                :style="dentitionMode === 'child'
                    ? 'min-width: 78px; height: 30px; padding: 0 10px; border: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; background: var(--crm-primary, #7c6cf6); color: #fff; box-shadow: 0 1px 2px rgba(124, 108, 246, .3);'
                    : 'min-width: 78px; height: 30px; padding: 0 10px; border: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; background: transparent; color: #4b5563; box-shadow: none;'"
                :aria-pressed="dentitionMode === 'child' ? 'true' : 'false'"
            >
                Trẻ em
            </button>
        </div>

        <div class="crm-tooth-toolbar-actions" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
            <span class="crm-selection-chip" style="display: inline-flex; align-items: center; min-height: 30px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 7px; background: #fff; font-size: 12px; font-weight: 700; color: #4b5563;">Đã chọn: <span x-text="selectedTeeth.length"></span></span>
            <button type="button" @click="toggleMultiSelectMode()" class="crm-btn crm-btn-sm" :class="multiSelectMode ? 'crm-btn-primary' : 'crm-btn-outline'" :aria-pressed="multiSelectMode ? 'true' : 'false'">Chọn nhiều</button>
            <button type="button" @click="clearSelection()" :disabled="selectedTeeth.length === 0" class="crm-btn crm-btn-outline crm-btn-sm disabled:opacity-50">Bỏ chọn</button>
            <button type="button" @click="openModal()" :disabled="selectedTeeth.length === 0" class="crm-btn crm-btn-primary crm-btn-sm disabled:opacity-50">Chẩn đoán răng đã chọn</button>
        </div>
    </div>

    <div class="overflow-x-auto pb-2">
        <div class="text-center">
            <div class="crm-tooth-grid" x-show="showAdultTeeth()" x-cloak>
                @foreach($adultUpper as $t)
                    <div class="selection-item" item-key="{{ $t }}" :class="isToothSelected({{ $t }}) ? 'is-selected' : ''">
                        <div class="crm-tooth-number mb-1">{{ $t }}</div>
                        <button
                            type="button"
                            class="tooth-item-cell"
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

            <div class="crm-tooth-grid" x-show="showChildTeeth()" x-cloak>
                @foreach($childUpper as $t)
                    <div class="selection-item" item-key="{{ $t }}" :class="isToothSelected({{ $t }}) ? 'is-selected' : ''">
                        <button
                            type="button"
                            class="tooth-item-cell"
                            :title="'Răng sữa ' + {{ $t }} + ': ' + getConditionsList({{ $t }}) + ' | ' + getToothTreatmentStateLabel({{ $t }})"
                            @click="toggleTooth({{ $t }}, $event)"
                        >
                            <div :class="getToothBoxClass({{ $t }}, true)"></div>
                        </button>
                        <div class="crm-tooth-number mt-1">{{ $t }}</div>
                    </div>
                @endforeach
            </div>

            <div class="crm-tooth-grid" x-show="showChildTeeth()" x-cloak>
                @foreach($childLower as $t)
                    <div class="selection-item" item-key="{{ $t }}" :class="isToothSelected({{ $t }}) ? 'is-selected' : ''">
                        <div class="crm-tooth-number mb-1">{{ $t }}</div>
                        <button
                            type="button"
                            class="tooth-item-cell"
                            :title="'Răng sữa ' + {{ $t }} + ': ' + getConditionsList({{ $t }}) + ' | ' + getToothTreatmentStateLabel({{ $t }})"
                            @click="toggleTooth({{ $t }}, $event)"
                        >
                            <div :class="getToothBoxClass({{ $t }}, true)"></div>
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="crm-tooth-grid" x-show="showAdultTeeth()" x-cloak>
                @foreach($adultLower as $t)
                    <div class="selection-item" item-key="{{ $t }}" :class="isToothSelected({{ $t }}) ? 'is-selected' : ''">
                        <button
                            type="button"
                            class="tooth-item-cell"
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
        * Dùng nút "Chọn nhiều" (hỗ trợ mobile) hoặc giữ phím "Ctrl/Command" để chọn nhiều răng trước khi chẩn đoán.
    </p>

    <div class="note-teeth mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-600">
        <div class="note-tooth-sign w-full text-gray-500">* chú thích hiện trạng răng</div>
        <div class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-gray-500"></span> Tình trạng hiện tại</div>
        <div class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-red-500"></span> Đang được điều trị</div>
        <div class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-green-500"></span> Hoàn thành điều trị</div>
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
                    <textarea x-model="modalNotes" rows="2" class="crm-textarea crm-textarea-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                </div>
            </div>

            <div class="crm-modal-footer">
                <button type="button" @click="saveDiagnosis()" class="crm-btn crm-btn-primary">Chọn</button>
                <button type="button" @click="closeModal()" class="crm-btn crm-btn-outline">Hủy bỏ</button>
            </div>
        </div>
    </div>
</div>
