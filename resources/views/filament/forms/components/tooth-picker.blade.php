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
    <div x-data="{
        state: $wire.$entangle('{{ $getStatePath() }}'),
        modalOpen: false,
        tab: 'adult', // adult, child
        
        toggleTooth(tooth) {
            if (!this.state) this.state = [];
            
            // Ensure state is an array
            if (!Array.isArray(this.state)) this.state = [];

            let index = this.state.indexOf(tooth);
            if (index === -1) {
                this.state.push(tooth);
            } else {
                this.state.splice(index, 1);
            }
        },

        isSelected(tooth) {
            return Array.isArray(this.state) && this.state.includes(tooth);
        },

        selectAll(list) {
            if (!this.state) this.state = [];
            list.forEach(t => {
                if (!this.state.includes(t)) this.state.push(t);
            });
        },

        deselectAll(list) {
            if (!this.state) return;
            this.state = this.state.filter(t => !list.includes(t));
        },
        
        getLabel() {
            if (!this.state || this.state.length === 0) return 'Chưa chọn răng';
            return 'Đã chọn: ' + this.state.join(', ');
        }
    }">
        <!-- Trigger Button -->
        <div class="flex items-center gap-3">
            <button type="button" @click="modalOpen = true"
                class="px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm font-medium text-gray-700">
                Chọn răng
            </button>
            <span x-text="getLabel()" class="text-sm text-gray-500"></span>
        </div>

        <!-- Modal -->
        <div x-show="modalOpen" style="display: none;"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" x-transition>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 relative flex flex-col max-h-[90vh]">

                <!-- Header -->
                <div class="flex justify-between items-center mb-4 border-b pb-4">
                    <h3 class="text-xl font-bold text-gray-800">CHỌN RĂNG</h3>
                    <button type="button" @click="modalOpen = false" class="text-gray-400 hover:text-gray-600">
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
                            <div class="grid gap-2" style="grid-template-columns: repeat(16, minmax(0, 1fr));">
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
                            <div class="grid gap-2" style="grid-template-columns: repeat(16, minmax(0, 1fr));">
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
                        <!-- Similar structure for Child teeth usually has fewer teeth, but key is logic -->
                        <div class="text-center p-4 bg-gray-50 rounded">
                            <p class="text-gray-500">Chức năng chọn răng sữa đang được cập nhật...</p>
                            <!-- Placeholder for child teeth implementation if needed -->
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 pt-4 border-t mt-4">
                    <button type="button" @click="modalOpen = false"
                        class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium">
                        Xác nhận
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>