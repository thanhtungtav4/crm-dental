<div class="border-b border-gray-200">
    <button type="button" @click="openSection = openSection === 'general' ? '' : 'general'" class="crm-exam-section-header">
        <svg class="h-4 w-4 transition" :class="openSection === 'general' ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        KHÁM TỔNG QUÁT
    </button>

    <div x-show="openSection === 'general'" x-cloak class="crm-exam-section-body">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="crm-field-card">
                <div class="crm-field-header">
                    <div>
                        <label class="crm-field-label">Bác sĩ khám</label>
                        <p class="crm-field-helper">Chọn người trực tiếp khám. Hệ thống tự lưu ngay sau khi bạn chọn.</p>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="relative flex-1" x-data="{ open: @entangle('showExaminingDoctorDropdown') }">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="examiningDoctorSearch"
                            x-on:focus="open = true"
                            @click.away="open = false"
                            placeholder="Chọn bác sĩ..."
                            class="crm-doctor-input block w-full rounded-md border-gray-300 bg-white pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        >
                        @if($examining_doctor_id)
                            <button type="button" wire:click="clearExaminingDoctor" class="crm-doctor-clear-btn absolute flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:text-red-500">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        @endif

                        <div x-show="open" x-cloak class="crm-doctor-dropdown absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-600 dark:bg-gray-800">
                            @forelse($examiningDoctors as $doctor)
                                <button type="button" wire:click="selectExaminingDoctor({{ $doctor->id }})" class="block w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700">{{ $doctor->name }}</button>
                            @empty
                                <div class="px-3 py-2 text-gray-500">Không tìm thấy</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <label class="crm-field-label mb-2 block">Ghi chú khám tổng quát</label>
                <textarea
                    wire:model.live.debounce.500ms="general_exam_notes"
                    rows="5"
                    placeholder="Ghi nhận triệu chứng, khám ngoài mặt, khám trong miệng hoặc nhận định ban đầu"
                    class="crm-textarea text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                ></textarea>
                <p class="crm-field-helper mt-2">Không cần bấm lưu. Nội dung sẽ tự động đồng bộ sau lần nhập cuối.</p>
            </div>

            <div class="crm-field-card">
                <div class="crm-field-header">
                    <div>
                        <label class="crm-field-label">Bác sĩ điều trị</label>
                        <p class="crm-field-helper">Dùng khi cần phân công khác với bác sĩ khám để các phiếu điều trị bám đúng người phụ trách.</p>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="relative flex-1" x-data="{ open: @entangle('showTreatingDoctorDropdown') }">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="treatingDoctorSearch"
                            x-on:focus="open = true"
                            @click.away="open = false"
                            placeholder="Chọn bác sĩ..."
                            class="crm-doctor-input block w-full rounded-md border-gray-300 bg-white pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        >
                        @if($treating_doctor_id)
                            <button type="button" wire:click="clearTreatingDoctor" class="crm-doctor-clear-btn absolute flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:text-red-500">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        @endif

                        <div x-show="open" x-cloak class="crm-doctor-dropdown absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-600 dark:bg-gray-800">
                            @forelse($treatingDoctors as $doctor)
                                <button type="button" wire:click="selectTreatingDoctor({{ $doctor->id }})" class="block w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700">{{ $doctor->name }}</button>
                            @empty
                                <div class="px-3 py-2 text-gray-500">Không tìm thấy</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <label class="crm-field-label mb-2 block">Kế hoạch điều trị</label>
                <textarea
                    wire:model.live.debounce.500ms="treatment_plan_note"
                    rows="5"
                    placeholder="Tóm tắt định hướng điều trị, thứ tự ưu tiên hoặc các lưu ý phối hợp tiếp theo"
                    class="crm-textarea text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                ></textarea>
                <p class="crm-field-helper mt-2">Nên ghi ngắn gọn theo từng bước để đội điều trị đọc nhanh ngay trên hồ sơ.</p>
            </div>
        </div>
    </div>
</div>
