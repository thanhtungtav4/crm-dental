<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.entangle('{{ $getStatePath() }}'),
            draftState: [],
            modalOpen: false,
            tab: 'adult',
            childTeeth: @js($childTeethFlat),

            init() {
                this.state = this.normalizeState(this.state);
                this.draftState = [...this.state];
                this.syncTabWithSelection(this.state);
            },

            normalizeState(value) {
                if (!Array.isArray(value)) {
                    return [];
                }

                return value.map((tooth) => String(tooth));
            },

            normalizeTooth(tooth) {
                return String(tooth);
            },

            syncTabWithSelection(list = this.draftState) {
                const hasChildTooth = Array.isArray(list)
                    && list.some((tooth) => this.childTeeth.includes(String(tooth)));

                this.tab = hasChildTooth ? 'child' : 'adult';
            },

            openPicker() {
                this.state = this.normalizeState(this.state);
                this.draftState = [...this.state];
                this.syncTabWithSelection(this.draftState);
                this.modalOpen = true;
            },

            cancelPicker() {
                this.draftState = this.normalizeState(this.state);
                this.syncTabWithSelection(this.draftState);
                this.modalOpen = false;
            },

            confirmPicker() {
                this.state = this.normalizeState(this.draftState);
                this.modalOpen = false;
            },

            toggleTooth(tooth) {
                this.draftState = this.normalizeState(this.draftState);

                const toothKey = this.normalizeTooth(tooth);
                const index = this.draftState.indexOf(toothKey);
                if (index === -1) {
                    this.draftState = [...this.draftState, toothKey];
                } else {
                    this.draftState = this.draftState.filter((currentTooth) => currentTooth !== toothKey);
                }
            },

            isSelected(tooth) {
                const toothKey = this.normalizeTooth(tooth);

                return Array.isArray(this.draftState) && this.draftState.includes(toothKey);
            },

            selectAll(list) {
                this.draftState = this.normalizeState(this.draftState);

                const merged = [...this.draftState];
                list.forEach((tooth) => {
                    const toothKey = this.normalizeTooth(tooth);

                    if (!merged.includes(toothKey)) {
                        merged.push(toothKey);
                    }
                });
                this.draftState = merged;
            },

            deselectAll(list) {
                this.draftState = this.normalizeState(this.draftState);

                const listKeys = list.map((tooth) => this.normalizeTooth(tooth));
                this.draftState = this.draftState.filter((tooth) => !listKeys.includes(this.normalizeTooth(tooth)));
            },

            getLabel() {
                const normalized = this.normalizeState(this.state);

                if (normalized.length === 0) {
                    return 'Chưa chọn răng';
                }

                return 'Đã chọn: ' + [...normalized]
                    .sort((a, b) => Number(a) - Number(b))
                    .join(', ');
            }
        }"
        @keydown.escape.window="if (modalOpen) { cancelPicker(); }"
    >
        <!-- Trigger Button -->
        <div class="flex items-center gap-3">
            <button type="button" @click="openPicker()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800">
                Chọn răng
            </button>
            <span x-text="getLabel()" class="text-sm text-gray-500 dark:text-gray-400"></span>
        </div>

        <!-- Modal -->
        <div x-show="modalOpen" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" x-transition @click.self="cancelPicker()">
            <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-900">

                <!-- Header -->
                <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-700">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">CHỌN RĂNG</h3>
                    <button type="button" @click="cancelPicker()" class="crm-modal-close-btn">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="mb-4 flex gap-4 border-b">
                    @foreach($tabs as $tabConfig)
                        <button type="button" @click="tab = '{{ $tabConfig['key'] }}'"
                            class="border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                            :class="tab === '{{ $tabConfig['key'] }}' ? 'border-primary-600 text-primary-600 dark:text-primary-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                            {{ $tabConfig['label'] }}
                        </button>
                    @endforeach
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto">
                    @foreach($toothGroups as $tabKey => $groups)
                        <div x-show="tab === '{{ $tabKey }}'" class="space-y-6">
                            @foreach($groups as $group)
                                <div>
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-200">{{ $group['label'] }}</span>
                                        <div class="space-x-2 text-xs">
                                            <button type="button" @click="selectAll(@js($group['teeth']))"
                                                class="text-primary-600 hover:underline">Chọn hết</button>
                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                            <button type="button" @click="deselectAll(@js($group['teeth']))"
                                                class="text-red-500 hover:underline">Bỏ chọn</button>
                                        </div>
                                    </div>
                                    <div class="grid gap-2 {{ $group['grid_class'] }}" style="{{ $group['grid_style'] }}">
                                        @foreach($group['teeth'] as $tooth)
                                            <button type="button" @click="toggleTooth({{ $tooth }})"
                                                class="{{ $group['button_classes'] }}"
                                                :class="isSelected({{ $tooth }}) ? '{{ $selectedButtonClasses }}' : '{{ $defaultButtonClasses }}'">
                                                <span class="text-xs font-bold"
                                                    :class="isSelected({{ $tooth }}) ? '{{ $selectedLabelClasses }}' : '{{ $defaultLabelClasses }}'">{{ $tooth }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <!-- Footer -->
                <div class="mt-4 flex justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button type="button" @click="cancelPicker()"
                        class="rounded-lg border border-gray-300 bg-white px-6 py-2 font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800">
                        Hủy
                    </button>
                    <button type="button" @click="confirmPicker()"
                        class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium">
                        Xác nhận
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
