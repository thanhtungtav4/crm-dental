{
    openSection: 'general',
    state: $wire.entangle('tooth_diagnosis_data'),
    dentitionMode: @entangle('dentition_mode'),
    defaultDentitionMode: @js($defaultDentitionMode),
    adultTeeth: @js(array_map('strval', array_merge($adultUpper, $adultLower))),
    childTeeth: @js(array_map('strval', array_merge($childUpper, $childLower))),
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
    multiSelectMode: false,

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
        if (!this.isToothVisible(tooth)) {
            return;
        }

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

    hasToothSign(tooth) {
        const data = this.getToothData(tooth);
        return Array.isArray(data.conditions) && data.conditions.length > 0;
    },

    clearSelection() {
        this.selectedTeeth = [];
    },

    toggleMultiSelectMode() {
        this.multiSelectMode = !this.multiSelectMode;
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
}
