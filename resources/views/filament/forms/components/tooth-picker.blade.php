@php
    $adultTeeth = [
        'upper' => [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28],
        'lower' => [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38],
    ];
    $childTeeth = [
        'upper' => [55, 54, 53, 52, 51, 61, 62, 63, 64, 65],
        'lower' => [85, 84, 83, 82, 81, 71, 72, 73, 74, 75],
    ];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.entangle('{{ $getStatePath() }}'),
            draftState: [],
            modalOpen: false,
            tab: 'adult',
            childTeeth: @js(array_map('strval', array_values(array_merge($childTeeth['upper'], $childTeeth['lower'])))),

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
                class="px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm font-medium text-gray-700">
                Chọn răng
            </button>
            <span x-text="getLabel()" class="text-sm text-gray-500"></span>
        </div>

        <!-- Modal -->
        <div x-show="modalOpen" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" x-transition @click.self="cancelPicker()">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 relative flex flex-col max-h-[90vh]">

                <!-- Header -->
                <div class="flex justify-between items-center mb-4 border-b pb-4">
                    <h3 class="text-xl font-bold text-gray-800">CHỌN RĂNG</h3>
                    <button type="button" @click="cancelPicker()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex gap-4 mb-4 border-b">
                    <button type="button" @click="tab = 'adult'"
                        class="px-4 py-2 font-medium text-sm transition-colors border-b-2"
                        :class="tab === 'adult' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Người lớn
                    </button>
                    <button type="button" @click="tab = 'child'"
                        class="px-4 py-2 font-medium text-sm transition-colors border-b-2"
                        :class="tab === 'child' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Trẻ em
                    </button>
                </div>

                <!-- Content -->
                <div class="overflow-y-auto flex-1">

                    <!-- Adult View -->
                    <div x-show="tab === 'adult'" class="space-y-6">
                        <!-- Upper -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-semibold text-gray-700 uppercase">Hàm trên</span>
                                <div class="text-xs space-x-2">
                                    <button type="button" @click="selectAll(@js($adultTeeth['upper']))"
                                        class="text-primary-600 hover:underline">Chọn hết</button>
                                    <span class="text-gray-300">|</span>
                                    <button type="button" @click="deselectAll(@js($adultTeeth['upper']))"
                                        class="text-red-500 hover:underline">Bỏ chọn</button>
                                </div>
                            </div>
                            <div class="grid gap-2 crm-tooth-picker-grid-16" style="grid-template-columns: repeat(16, minmax(0, 1fr));">
                                @foreach($adultTeeth['upper'] as $t)
                                    <button type="button" @click="toggleTooth({{ $t }})"
                                        class="h-12 border rounded flex items-center justify-center transition-all bg-white hover:shadow-md"
                                        :class="isSelected({{ $t }}) ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500 ring-offset-1' : 'border-gray-200'">
                                        <span class="text-xs font-bold"
                                            :class="isSelected({{ $t }}) ? 'text-primary-700' : 'text-gray-600'">{{ $t }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Lower -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-semibold text-gray-700 uppercase">Hàm dưới</span>
                                <div class="text-xs space-x-2">
                                    <button type="button" @click="selectAll(@js($adultTeeth['lower']))"
                                        class="text-primary-600 hover:underline">Chọn hết</button>
                                    <span class="text-gray-300">|</span>
                                    <button type="button" @click="deselectAll(@js($adultTeeth['lower']))"
                                        class="text-red-500 hover:underline">Bỏ chọn</button>
                                </div>
                            </div>
                            <div class="grid gap-2 crm-tooth-picker-grid-16" style="grid-template-columns: repeat(16, minmax(0, 1fr));">
                                @foreach($adultTeeth['lower'] as $t)
                                    <button type="button" @click="toggleTooth({{ $t }})"
                                        class="h-12 border rounded flex items-center justify-center transition-all bg-white hover:shadow-md"
                                        :class="isSelected({{ $t }}) ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500 ring-offset-1' : 'border-gray-200'">
                                        <span class="text-xs font-bold"
                                            :class="isSelected({{ $t }}) ? 'text-primary-700' : 'text-gray-600'">{{ $t }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Child View -->
                    <div x-show="tab === 'child'" class="space-y-6">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-semibold text-gray-700 uppercase">Răng sữa hàm trên</span>
                                <div class="text-xs space-x-2">
                                    <button type="button" @click="selectAll(@js($childTeeth['upper']))"
                                        class="text-primary-600 hover:underline">Chọn hết</button>
                                    <span class="text-gray-300">|</span>
                                    <button type="button" @click="deselectAll(@js($childTeeth['upper']))"
                                        class="text-red-500 hover:underline">Bỏ chọn</button>
                                </div>
                            </div>
                            <div class="grid gap-2 crm-tooth-picker-grid-10" style="grid-template-columns: repeat(10, minmax(0, 1fr));">
                                @foreach($childTeeth['upper'] as $t)
                                    <button type="button" @click="toggleTooth({{ $t }})"
                                        class="h-11 border rounded flex items-center justify-center transition-all bg-white hover:shadow-md"
                                        :class="isSelected({{ $t }}) ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500 ring-offset-1' : 'border-gray-200'">
                                        <span class="text-xs font-bold"
                                            :class="isSelected({{ $t }}) ? 'text-primary-700' : 'text-gray-600'">{{ $t }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-semibold text-gray-700 uppercase">Răng sữa hàm dưới</span>
                                <div class="text-xs space-x-2">
                                    <button type="button" @click="selectAll(@js($childTeeth['lower']))"
                                        class="text-primary-600 hover:underline">Chọn hết</button>
                                    <span class="text-gray-300">|</span>
                                    <button type="button" @click="deselectAll(@js($childTeeth['lower']))"
                                        class="text-red-500 hover:underline">Bỏ chọn</button>
                                </div>
                            </div>
                            <div class="grid gap-2 crm-tooth-picker-grid-10" style="grid-template-columns: repeat(10, minmax(0, 1fr));">
                                @foreach($childTeeth['lower'] as $t)
                                    <button type="button" @click="toggleTooth({{ $t }})"
                                        class="h-11 border rounded flex items-center justify-center transition-all bg-white hover:shadow-md"
                                        :class="isSelected({{ $t }}) ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500 ring-offset-1' : 'border-gray-200'">
                                        <span class="text-xs font-bold"
                                            :class="isSelected({{ $t }}) ? 'text-primary-700' : 'text-gray-600'">{{ $t }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 pt-4 border-t mt-4">
                    <button type="button" @click="cancelPicker()"
                        class="px-6 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium">
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
