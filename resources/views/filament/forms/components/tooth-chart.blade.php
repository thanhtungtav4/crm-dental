@php
    $conditions = \App\Models\ToothCondition::all();
    $conditionPalette = $conditions->map(fn($condition) => [
        'code' => $condition->code,
        'name' => $condition->name,
        'toneClass' => 'crm-tooth-tone-' . $condition->id,
    ])->values();
    
    // Define teeth rows as per screenshot
    // Adult Upper: 18-11 then 21-28
    // Child Upper: 55-51 then 61-65 (Centered below adult)
    // Child Lower: 85-81 then 71-75 (Centered above adult lower)
    // Adult Lower: 48-41 then 31-38
    
    $adultUpper = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
    // Child teeth are centered. We'll use spacer divs to align them visually under 15-25 range
    $childUpper = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65]; 
    $childLower = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];
    $adultLower = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
@endphp

<style>
    .crm-tooth-tone-default {
        color: #e5e7eb;
    }

    .crm-tooth-tone-fallback {
        color: #3b82f6;
    }

    @foreach($conditions as $condition)
        .crm-tooth-tone-{{ $condition->id }} {
            color: {{ $condition->color ?? '#3b82f6' }};
        }
    @endforeach
</style>

<div 
    x-data="{
        state: $wire.entangle('data.tooth_diagnosis_data'),
        selectedTooth: null,
        modalOpen: false,
        otherDiagnosis: '',
        conditions: @js($conditionPalette),
        
        getToothData(tooth) {
            return this.state?.[tooth] || { conditions: [], status: 'planned', notes: '' };
        },

        getToothToneClass(tooth) {
            let data = this.getToothData(tooth);
            if (!data.conditions || data.conditions.length === 0) return 'crm-tooth-tone-default';
            
            // Priority coloring: if multiple, take the last one
            let code = data.conditions[data.conditions.length - 1]; 
            let cond = this.conditions.find(c => c.code === code);

            return cond ? cond.toneClass : 'crm-tooth-tone-fallback';
        },

        getConditionsList(tooth) {
            let data = this.getToothData(tooth);
            if (!data.conditions || data.conditions.length === 0) return 'Bình thường';
            
            return data.conditions.map(code => {
                let c = this.conditions.find(item => item.code === code);
                return c ? c.name : code;
            }).join(', ');
        },

        getConditionLabels(tooth) {
            let data = this.getToothData(tooth);
            if (!data.conditions || data.conditions.length === 0) return '';
            
            return data.conditions.map(code => {
                // Return short code for display on tooth
                return code;
            }).join(' ');
        },
        
        openModal(tooth) {
            this.selectedTooth = tooth;
            this.otherDiagnosis = this.getToothData(tooth).notes || '';
            this.modalOpen = true;
        },

        closeModal() {
            this.modalOpen = false;
            this.selectedTooth = null;
        },

        saveDiagnosis() {
           if(this.selectedTooth) {
                if (!this.state) this.state = {};
                // Ensure structure
                if (!this.state[this.selectedTooth]) this.state[this.selectedTooth] = { conditions: [], status: 'planned', notes: '' };
                this.state[this.selectedTooth].notes = this.otherDiagnosis;
           }
           this.closeModal();
        },

        toggleCondition(code) {
            let tooth = this.selectedTooth;
            if (!this.state) this.state = {};
            if (!this.state[tooth]) this.state[tooth] = { conditions: [], status: 'planned', notes: '' };
            if (!this.state[tooth].conditions) this.state[tooth].conditions = [];

            let index = this.state[tooth].conditions.indexOf(code);
            if (index === -1) {
                this.state[tooth].conditions.push(code);
            } else {
                this.state[tooth].conditions.splice(index, 1);
            }
        },

        hasCondition(code) {
             if (!this.selectedTooth || !this.state || !this.state[this.selectedTooth] || !this.state[this.selectedTooth].conditions) return false;
            return this.state[this.selectedTooth].conditions.includes(code);
        }
    }"
    class="w-full bg-white p-6 rounded-xl border border-gray-200 select-none shadow-sm"
>
    <!-- Chart Container -->
    <div class="max-w-6xl mx-auto flex flex-col items-center">
        
        <!-- Legend (Simplified) -->
        <div class="flex flex-wrap gap-4 justify-center mb-8 text-xs text-gray-500 bg-gray-50/50 py-2 px-6 rounded-full border border-gray-100">
            <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm border border-gray-300 bg-gray-100"></span> Bình thường</div>
             @foreach($conditions->unique('category')->pluck('category') as $cat)
                @php 
                    $dotClass = match($cat) {
                        'Bệnh lý' => 'bg-red-500', 
                        'Phục hình' => 'bg-blue-500', 
                        'Thẩm mỹ' => 'bg-yellow-500', 
                        'Hiện trạng' => 'bg-slate-800', 
                        default => 'bg-gray-500'
                    };
                @endphp
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm {{ $dotClass }}"></span> {{ $cat }}</div>
             @endforeach
        </div>

        <!-- ROW 1: Adult Upper (18-28) -->
        <div class="flex gap-1 md:gap-3 justify-center mb-6">
            @foreach($adultUpper as $t)
                <div class="flex flex-col items-center group relative cursor-pointer" @click="openModal({{ $t }})">
                     <span class="text-xs text-gray-500 mb-1 font-medium group-hover:text-primary-600 transition-colors">{{ $t }}</span>
                     <div 
                        class="w-10 h-10 md:w-12 md:h-12 border border-transparent rounded hover:bg-gray-50 transition-all duration-200 flex items-center justify-center relative group-hover:scale-110"
                        :title="'Răng ' + {{ $t }} + ': ' + getConditionsList({{ $t }})"
                     >
                        {{-- SVG Tooth Icon - Color the FILL --}}
                        <svg viewBox="0 0 24 24" class="w-full h-full transition-colors duration-300 drop-shadow-sm" 
                             :class="getToothToneClass({{ $t }})" 
                             fill="currentColor" 
                        >
                             <!-- Simple Molar Shape -->
                             <path d="M7 2c-2.2 0-4 1.8-4 4v10c0 3 2.5 5.5 5.5 5.5h7c3 0 5.5-2.5 5.5-5.5V6c0-2.2-1.8-4-4-4H7zm0 2h10c1.1 0 2 .9 2 2v10c0 1.9-1.6 3.5-3.5 3.5h-7C6.6 20 5 18.4 5 16.5V6c0-1.1.9-2 2-2z" class="opacity-10 text-gray-900" />
                             {{-- Main body --}}
                             <path d="M12 2C8 2 5 5 5 9c0 4 3 7 3 10h8c0-3 3-6 3-10 0-4-3-7-7-7z"/>
                        </svg>
                        {{-- Condition Labels Overlay --}}
                        <div 
                            x-show="getConditionLabels({{ $t }})" 
                            x-text="getConditionLabels({{ $t }})"
                            class="absolute inset-0 flex items-center justify-center text-[8px] font-bold text-white drop-shadow-md leading-tight text-center crm-tooth-label-shadow"
                        ></div>
                     </div>
                </div>
                {{-- Add space between 11 and 21 (Quadrants) --}}
                @if($t === 11) <div class="w-6 md:w-10 border-r border-dashed border-gray-200 mx-2"></div> @endif
            @endforeach
        </div>

        <!-- ROW 2: Child Upper (55-65) -->
        <div class="flex gap-1 md:gap-3 justify-center mb-6">
             {{-- Spacers to center roughly under 15-25 range --}}
             <div class="w-0 md:w-12"></div> 
            @foreach($childUpper as $t)
                <div class="flex flex-col items-center group cursor-pointer" @click="openModal({{ $t }})">
                     <div 
                        class="w-8 h-8 md:w-10 md:h-10 border border-transparent rounded hover:bg-gray-50 transition-all duration-200 flex items-center justify-center relative group-hover:scale-110"
                        :title="'Răng sữa ' + {{ $t }} + ': ' + getConditionsList({{ $t }})"
                     >
                        <svg viewBox="0 0 24 24" class="w-full h-full transition-colors duration-300 drop-shadow-sm" :class="getToothToneClass({{ $t }})" fill="currentColor">
                             <path d="M12 4C9 4 7 6 7 9c0 3 2 5 2 7h6c0-2 2-4 2-7 0-3-2-5-5-5z"/>
                        </svg>
                        <div x-show="getConditionLabels({{ $t }})" x-text="getConditionLabels({{ $t }})" class="absolute inset-0 flex items-center justify-center text-[7px] font-bold text-white drop-shadow-md crm-tooth-label-shadow"></div>
                     </div>
                     <span class="text-[10px] text-gray-400 mt-1 group-hover:text-primary-600">{{ $t }}</span>
                </div>
                 @if($t === 51) <div class="w-6 md:w-10 border-r border-dashed border-gray-200 mx-2"></div> @endif
            @endforeach
             <div class="w-0 md:w-12"></div>
        </div>

        <!-- ROW 3: Child Lower (85-75) -->
        <div class="flex gap-1 md:gap-3 justify-center mt-2 mb-6">
             <div class="w-0 md:w-12"></div>
            @foreach($childLower as $t)
                <div class="flex flex-col items-center group cursor-pointer" @click="openModal({{ $t }})">
                     <span class="text-[10px] text-gray-400 mb-1 group-hover:text-primary-600">{{ $t }}</span>
                     <div 
                        class="w-8 h-8 md:w-10 md:h-10 border border-transparent rounded hover:bg-gray-50 transition-all duration-200 flex items-center justify-center relative group-hover:scale-110"
                        :title="'Răng sữa ' + {{ $t }} + ': ' + getConditionsList({{ $t }})"
                     >
                         <svg viewBox="0 0 24 24" class="w-full h-full transition-colors duration-300 drop-shadow-sm transform rotate-180" :class="getToothToneClass({{ $t }})" fill="currentColor">
                             <path d="M12 4C9 4 7 6 7 9c0 3 2 5 2 7h6c0-2 2-4 2-7 0-3-2-5-5-5z"/>
                        </svg>
                        <div x-show="getConditionLabels({{ $t }})" x-text="getConditionLabels({{ $t }})" class="absolute inset-0 flex items-center justify-center text-[7px] font-bold text-white drop-shadow-md crm-tooth-label-shadow"></div>
                     </div>
                </div>
                 @if($t === 81) <div class="w-6 md:w-10 border-r border-dashed border-gray-200 mx-2"></div> @endif
            @endforeach
             <div class="w-0 md:w-12"></div>
        </div>

        <!-- ROW 4: Adult Lower (48-38) -->
         <div class="flex gap-1 md:gap-3 justify-center mt-1">
            @foreach($adultLower as $t)
                <div class="flex flex-col items-center group cursor-pointer" @click="openModal({{ $t }})">
                     <div 
                        class="w-10 h-10 md:w-12 md:h-12 border border-transparent rounded hover:bg-gray-50 transition-all duration-200 flex items-center justify-center relative group-hover:scale-110"
                        :title="'Răng ' + {{ $t }} + ': ' + getConditionsList({{ $t }})"
                     >
                        <svg viewBox="0 0 24 24" class="w-full h-full transition-colors duration-300 drop-shadow-sm transform rotate-180" :class="getToothToneClass({{ $t }})" fill="currentColor">
                             <path d="M12 2C8 2 5 5 5 9c0 4 3 7 3 10h8c0-3 3-6 3-10 0-4-3-7-7-7z"/>
                        </svg>
                        <div x-show="getConditionLabels({{ $t }})" x-text="getConditionLabels({{ $t }})" class="absolute inset-0 flex items-center justify-center text-[8px] font-bold text-white drop-shadow-md crm-tooth-label-shadow"></div>
                     </div>
                      <span class="text-xs text-gray-500 mt-1 font-medium group-hover:text-primary-600">{{ $t }}</span>
                </div>
                @if($t === 41) <div class="w-6 md:w-10 border-r border-dashed border-gray-200 mx-2"></div> @endif
            @endforeach
        </div>

        <div class="mt-8 pt-4 w-full text-center">
            <p class="text-sm text-gray-500 italic mb-4">
                * Bạn có thể chọn 1 tình trạng cho nhiều răng khác nhau bằng cách giữ phím "Ctrl" + chọn "Răng số..." mà bạn muốn tạo thủ thuật điều trị.
            </p>
             <!-- Other Diagnosis Input -->
            <div class="w-full flex items-center gap-4 max-w-4xl mx-auto bg-white p-3 rounded-lg border border-gray-200 shadow-sm">
                 <span class="text-sm font-semibold text-gray-700 whitespace-nowrap">(*) Chẩn đoán khác</span>
                 <input 
                    type="text" 
                    placeholder="Nhập chẩn đoán hoặc ghi chú khác..." 
                    class="flex-1 border-0 focus:ring-0 text-sm py-1 px-2"
                 >
            </div>
        </div>

    </div>

    <!-- Modal for Condition Selection -->
    <div 
        x-show="modalOpen" 
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        x-transition
    >
        <div 
            class="bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh]"
            @click.away="closeModal()"
        >
            <!-- Header -->
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50 rounded-t-xl">
                 <h3 class="text-xl font-bold text-gray-800 uppercase">RĂNG <span x-text="selectedTooth" class="text-primary-600"></span></h3>
                <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Body: 2 Columns of Checkboxes -->
            <div class="p-6 overflow-y-auto flex-1">
                <h4 class="text-base font-medium text-gray-700 mb-4 px-1">Chọn tình trạng của răng</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-3">
                    @foreach($conditions as $condition)
                    <label 
                        class="flex items-center p-2 rounded cursor-pointer hover:bg-gray-100 transition-colors group"
                        :class="hasCondition('{{ $condition->code }}') ? 'bg-primary-50' : ''"
                    >
                        <div class="flex items-center h-5">
                            <input 
                                type="checkbox" 
                                value="{{ $condition->code }}" 
                                @click="toggleCondition('{{ $condition->code }}')"
                                :checked="hasCondition('{{ $condition->code }}')"
                                class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer"
                            >
                        </div>
                        <div class="ml-3 text-sm flex-1">
                            <span class="font-medium text-gray-700 group-hover:text-gray-900 leading-none block pt-0.5">{{ $condition->name }}</span>
                        </div>
                    </label>
                    @endforeach
                </div>
                
                 <div class="mt-6 pt-4 border-t">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú cho răng này (nếu có):</label>
                    <textarea 
                        x-model="otherDiagnosis" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" 
                        rows="2"
                    ></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t flex justify-end gap-3 rounded-b-xl">
                 <button 
                    type="button"
                    @click="closeModal()" 
                    class="px-6 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium transition-colors"
                >
                    Hủy bỏ
                </button>
                <button 
                    type="button"
                    @click="saveDiagnosis()"
                    class="px-8 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 shadow-lg shadow-primary-500/30 font-medium transition-colors"
                >
                    Chọn
                </button>
            </div>
        </div>
    </div>
</div>
