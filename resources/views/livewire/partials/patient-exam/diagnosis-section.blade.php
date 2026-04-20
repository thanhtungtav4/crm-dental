<div>
    <button type="button" @click="openSection = openSection === 'diagnosis' ? '' : 'diagnosis'" class="crm-exam-section-header">
        <svg class="h-4 w-4 transition" :class="openSection === 'diagnosis' ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        CHẨN ĐOÁN VÀ ĐIỀU TRỊ
    </button>

    <div x-show="openSection === 'diagnosis'" x-cloak class="crm-exam-section-body">
        <div class="crm-tooth-chart">
            <div class="crm-tooth-toolbar mb-3">
                <div class="crm-dentition-toggle" role="tablist" aria-label="Chế độ sơ đồ răng">
                    @foreach($diagnosisDentitionOptions as $option)
                        <button
                            type="button"
                            @click="setDentitionMode('{{ $option['mode'] }}')"
                            class="crm-dentition-option"
                            :class="dentitionMode === '{{ $option['mode'] }}' ? 'is-active' : ''"
                            :aria-pressed="dentitionMode === '{{ $option['mode'] }}' ? 'true' : 'false'"
                        >
                            {{ $option['label'] }}
                        </button>
                    @endforeach
                </div>

                <div class="crm-tooth-toolbar-actions">
                    <span class="crm-selection-chip">Đã chọn: <span x-text="selectedTeeth.length"></span></span>
                    <button type="button" @click="toggleMultiSelectMode()" class="crm-btn crm-btn-sm" :class="multiSelectMode ? 'crm-btn-primary' : 'crm-btn-outline'" :aria-pressed="multiSelectMode ? 'true' : 'false'">Chọn nhiều</button>
                    <button type="button" @click="clearSelection()" :disabled="selectedTeeth.length === 0" class="crm-btn crm-btn-outline crm-btn-sm disabled:opacity-50">Bỏ chọn</button>
                    <button type="button" @click="openModal()" :disabled="selectedTeeth.length === 0" class="crm-btn crm-btn-primary crm-btn-sm disabled:opacity-50">Chẩn đoán răng đã chọn</button>
                </div>
            </div>

            <div class="overflow-x-auto pb-2">
                <div class="text-center">
                    @include('filament.forms.components.partials.tooth-chart-rows', ['rows' => $diagnosisToothRows])
                </div>
            </div>

            <p class="mt-3 text-center text-xs italic text-gray-500 dark:text-gray-400">
                {{ $diagnosisSelectionHint }}
            </p>

            <div class="mt-4 border-t border-gray-300 pt-3 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="min-w-[140px] text-sm font-medium text-gray-700 dark:text-gray-300">(*) Chẩn đoán khác</label>
                    <div class="relative flex-1" @click.away="otherDiagnosisOpen = false">
                        <div class="flex flex-wrap items-center gap-2 rounded-md border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <template x-for="(tag, index) in otherDiagnosisTags" :key="tag + index">
                                <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-1 text-xs text-primary-700 dark:bg-primary-500/15 dark:text-primary-100">
                                    <span x-text="tag"></span>
                                    <button type="button" @click="removeOtherDiagnosisTag(index)" class="crm-other-diagnosis-tag-remove">×</button>
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
                                class="min-w-[160px] flex-1 border-0 bg-transparent p-0 text-sm text-gray-700 focus:ring-0 dark:text-gray-100"
                            >
                        </div>

                        <div
                            x-show="otherDiagnosisOpen"
                            x-cloak
                            class="crm-other-diagnosis-dropdown absolute z-40 mt-1 w-full rounded-md border border-gray-200 bg-white text-sm shadow-lg dark:border-gray-700 dark:bg-gray-900"
                        >
                            <template x-for="group in groupedOtherDiagnosisOptions()" :key="group.group">
                                <div>
                                    <div class="px-3 py-1 text-xs font-semibold text-gray-500 dark:text-gray-400" x-text="group.group"></div>
                                    <template x-for="option in group.items" :key="option.code">
                                        <button
                                            type="button"
                                            class="block w-full px-3 py-2 text-left text-gray-700 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-800"
                                            @click="selectOtherDiagnosisOption(option)"
                                        >
                                            <span x-text="option.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <div x-show="groupedOtherDiagnosisOptions().length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Không có kết quả</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('filament.forms.components.partials.tooth-chart-legend', ['legendItems' => $diagnosisTreatmentLegend])
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
                <h3 class="text-base font-bold uppercase text-gray-800 dark:text-gray-100">
                    RĂNG <span x-text="selectedTeeth.join(', ')" class="text-primary-600"></span>
                </h3>
                <button type="button" @click="closeModal()" class="crm-modal-close-btn">✕</button>
            </div>

            <div class="crm-modal-body">
                <h4 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-200">Chọn tình trạng của răng</h4>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach($conditions as $condition)
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800/70" :class="hasCondition(@js((string) $condition->code)) ? 'bg-primary-50 dark:bg-primary-500/15' : ''">
                            <input type="checkbox" @click="toggleCondition(@js((string) $condition->code))" :checked="hasCondition(@js((string) $condition->code))" class="h-4 w-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $condition->name }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Ghi chú áp dụng cho các răng đã chọn (nếu có)</label>
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
