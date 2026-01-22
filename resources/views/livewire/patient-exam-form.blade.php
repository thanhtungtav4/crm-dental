<div class="space-y-6">
    {{-- 1. Khám tổng quát --}}
    <div x-data="{ openSection: 'general' }"
         class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <button type="button" @click="openSection = openSection === 'general' ? '' : 'general'"
                class="w-full flex justify-between items-center px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <span class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                KHÁM TỔNG QUÁT
            </span>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSection === 'general' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        
        <div x-show="openSection === 'general'" x-collapse class="border-t border-gray-100 dark:border-gray-700">
            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Left Column: Bác sĩ khám + Note --}}
                <div class="space-y-4">
                    <div class="relative" x-data="{ open: @entangle('showExaminingDoctorDropdown') }">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bác sĩ khám</label>
                        <div class="relative">
                            <input type="text" 
                                   wire:model.live="examiningDoctorSearch"
                                   placeholder="Chọn bác sĩ..."
                                   @focus="open = true"
                                   @click.away="open = false"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm"
                                   value="{{ $this->examiningDoctorName }}"
                            >
                            @if($examining_doctor_id)
                                <button wire:click="$set('examining_doctor_id', null)" class="absolute right-2 top-2 text-gray-400 hover:text-red-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @endif
                        </div>
                        
                        <div x-show="open" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 max-h-60 overflow-auto">
                            @forelse($examiningDoctors as $doctor)
                                <div wire:click="selectExaminingDoctor({{ $doctor->id }})" 
                                     class="px-4 py-2 hover:bg-blue-50 dark:hover:bg-gray-600 cursor-pointer text-sm">
                                    {{ $doctor->name }}
                                </div>
                            @empty
                                <div class="px-4 py-2 text-gray-500 text-sm">Không tìm thấy bác sĩ</div>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <textarea wire:model.live.debounce.500ms="general_exam_notes" 
                                  rows="6"
                                  class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                  placeholder="Nhập khám tổng quát..."></textarea>
                    </div>
                </div>

                {{-- Right Column: Bác sĩ điều trị + Plan --}}
                <div class="space-y-4">
                    <div class="relative" x-data="{ open: @entangle('showTreatingDoctorDropdown') }">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bác sĩ điều trị</label>
                        <div class="relative">
                            <input type="text" 
                                   wire:model.live="treatingDoctorSearch"
                                   placeholder="Chọn bác sĩ..."
                                   @focus="open = true"
                                   @click.away="open = false"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm"
                                   value="{{ $this->treatingDoctorName }}"
                            >
                            @if($treating_doctor_id)
                                <button wire:click="$set('treating_doctor_id', null)" class="absolute right-2 top-2 text-gray-400 hover:text-red-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @endif
                        </div>
                        
                        <div x-show="open" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 max-h-60 overflow-auto">
                            @forelse($treatingDoctors as $doctor)
                                <div wire:click="selectTreatingDoctor({{ $doctor->id }})" 
                                     class="px-4 py-2 hover:bg-blue-50 dark:hover:bg-gray-600 cursor-pointer text-sm">
                                    {{ $doctor->name }}
                                </div>
                            @empty
                                <div class="px-4 py-2 text-gray-500 text-sm">Không tìm thấy bác sĩ</div>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <textarea wire:model.live.debounce.500ms="treatment_plan_note" 
                                  rows="6"
                                  class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                  placeholder="Nhập kế hoạch điều trị..."></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Chỉ định --}}
    <div x-data="{ openSection: 'indications' }"
         class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <button type="button" @click="openSection = openSection === 'indications' ? '' : 'indications'"
                class="w-full flex justify-between items-center px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <span class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                CHỈ ĐỊNH <span class="text-sm font-normal text-gray-500 ml-2">(Thêm chỉ định như Chụp X-Quang, Xét nghiệm máu)</span>
            </span>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSection === 'indications' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        
        <div x-show="openSection === 'indications'" x-collapse class="border-t border-gray-100 dark:border-gray-700">
            <div class="p-5">
                {{-- Checkbox Grid --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    @foreach($indicationTypes as $key => $label)
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   value="{{ $key }}" 
                                   wire:click="toggleIndication('{{ $key }}')"
                                   @if(in_array($key, $indications)) checked @endif
                                   class="form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                {{-- Upload Areas for Selected Indications --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    @foreach($indications as $type)
                        @if(array_key_exists($type, $indicationTypes))
                            <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 flex flex-col items-center justify-center text-center relative group">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $indicationTypes[$type] }}</h4>
                                
                                {{-- Upload Box --}}
                                <div class="w-full aspect-square bg-gray-50 dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700 flex flex-col items-center justify-center cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors relative">
                                    <input type="file" 
                                           wire:model="tempUploads.{{ $type }}" 
                                           multiple 
                                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                    
                                    <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs text-gray-500 font-medium">Thêm ảnh</span>
                                    <span class="text-[10px] text-gray-400">hoặc kéo thả</span>
                                </div>

                                {{-- Image Preview List for this Type --}}
                                @if(isset($indicationImages[$type]) && count($indicationImages[$type]) > 0)
                                    <div class="w-full mt-3 grid grid-cols-3 gap-2">
                                        @foreach($indicationImages[$type] as $index => $imagePath)
                                            <div class="relative group/img aspect-square rounded-md overflow-hidden bg-gray-100">
                                                <img src="{{ Storage::url($imagePath) }}" class="w-full h-full object-cover">
                                                <button wire:click="removeImage('{{ $type }}', {{ $index }})" 
                                                        class="absolute top-0 right-0 p-0.5 bg-red-500 text-white rounded-bl opacity-0 group-hover/img:opacity-100 transition-opacity">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                
                                {{-- Loading Indicator --}}
                                <div wire:loading wire:target="tempUploads.{{ $type }}" class="absolute inset-0 bg-white/50 flex items-center justify-center z-20">
                                    <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
