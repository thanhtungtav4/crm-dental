<div class="border-b border-gray-200">
    <button type="button" @click="openSection = openSection === 'indications' ? '' : 'indications'" class="crm-exam-section-header">
        <svg class="h-4 w-4 transition" :class="openSection === 'indications' ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        CHỈ ĐỊNH
        <span class="text-xs font-normal italic text-gray-500">(Thêm chỉ định như Chụp X-Quang, Xét nghiệm máu)</span>
    </button>

    <div x-show="openSection === 'indications'" x-cloak class="crm-exam-section-body">
        <div class="crm-exam-section-meta">
            <p class="crm-field-helper">
                Chọn chỉ định cần thu thập bằng chứng. Mỗi chỉ định có thể kéo thả hoặc dán ảnh trực tiếp để đội lâm sàng thao tác nhanh hơn.
            </p>
            @if(!empty($selectedIndicationUploadTypes))
                <span class="crm-section-chip">{{ count($selectedIndicationUploadTypes) }} chỉ định đang mở</span>
            @endif
        </div>

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

        @foreach($selectedIndicationUploadTypes as $type)
            <div class="mt-4 rounded-md border border-dashed border-gray-300 p-3 dark:border-gray-600" wire:key="indication-upload-{{ $sessionCard['id'] }}-{{ $type }}">
                <div class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">{{ $indicationTypes[$type] ?? strtoupper($type) }}</div>
                @if (!empty($indicationImages[$type]))
                    <div class="mb-2 flex flex-wrap gap-2">
                        @foreach ($indicationImages[$type] as $index => $image)
                            <div class="relative h-10 w-10 overflow-hidden rounded border border-gray-200">
                                <img src="{{ Storage::url($image) }}" class="h-full w-full object-cover">
                                <button type="button" wire:click="removeImage('{{ $type }}', {{ $index }})" class="absolute inset-0 hidden items-center justify-center bg-black/50 text-white hover:flex">×</button>
                            </div>
                        @endforeach
                    </div>
                @endif
                <label class="inline-flex cursor-pointer items-center gap-2 rounded border border-dashed border-primary-300 px-3 py-2 text-xs font-medium text-primary-600 hover:bg-primary-50">
                    Thêm ảnh hoặc kéo thả
                    <input type="file" wire:model="tempUploads.{{ $type }}" multiple class="hidden" x-ref="indicationInput_{{ $type }}" />
                </label>
                <div
                    class="crm-upload-dropzone mt-2 rounded-md border border-dashed border-gray-300 bg-white px-3 py-3 text-xs text-gray-500 dark:border-gray-600 dark:bg-gray-900"
                    @paste.prevent="handleIndicationPaste(@js($type), $event)"
                    @drop.prevent="handleIndicationDrop(@js($type), $event)"
                    @dragover.prevent
                >
                    Dán ảnh vào đây hoặc thả file từ máy tính
                </div>
                <div wire:loading.flex wire:target="tempUploads.{{ $type }}" class="crm-upload-status">
                    Đang tải ảnh cho {{ $indicationTypes[$type] ?? strtoupper($type) }}...
                </div>
            </div>
        @endforeach

        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/60">
            <div class="mb-2 flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Clinical evidence completeness</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Đã đạt {{ (int) data_get($evidenceChecklist, 'fulfilled', 0) }}/{{ (int) data_get($evidenceChecklist, 'required', 0) }} chỉ định cần ảnh.
                    </div>
                </div>
                @if(filled($medicalRecordActionUrl))
                    <a
                        href="{{ $medicalRecordActionUrl }}"
                        target="_blank"
                        class="crm-btn crm-btn-outline h-8 px-3 text-xs"
                    >
                        Mở bệnh án EMR
                    </a>
                @endif
            </div>

            <div class="mb-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    class="h-full rounded-full bg-emerald-500 transition-all"
                    style="width: {{ max(0, min(100, (int) data_get($evidenceChecklist, 'completion_percent', 0))) }}%;"
                ></div>
            </div>

            @if(!empty(data_get($evidenceChecklist, 'missing_labels', [])))
                <div class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-600/50 dark:bg-amber-900/20 dark:text-amber-200">
                    Thiếu ảnh cho: {{ implode(', ', (array) data_get($evidenceChecklist, 'missing_labels', [])) }}.
                </div>
            @endif

            @if(!empty(data_get($evidenceChecklist, 'quality_warnings', [])))
                <div class="mb-3 space-y-1">
                    @foreach((array) data_get($evidenceChecklist, 'quality_warnings', []) as $warning)
                        <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-700/60 dark:bg-rose-900/20 dark:text-rose-200">
                            {{ $warning }}
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($mediaTimeline))
                <div>
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Timeline ảnh lâm sàng gần nhất</div>
                    <div class="max-h-56 space-y-2 overflow-auto pr-1">
                        @foreach(array_slice($mediaTimeline, 0, 12) as $mediaEntry)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-gray-200 px-3 py-2 text-xs dark:border-gray-700">
                                <div class="min-w-[220px]">
                                    <div class="font-medium text-gray-800 dark:text-gray-100">
                                        #{{ $mediaEntry['id'] }} · {{ strtoupper((string) ($mediaEntry['modality'] ?? 'photo')) }} · {{ (string) ($mediaEntry['phase'] ?? 'unspecified') }}
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400">
                                        {{ (string) ($mediaEntry['captured_at'] ?? '-') }}
                                        @if(!empty($mediaEntry['exam_session_id']))
                                            · Phiếu khám #{{ (int) $mediaEntry['exam_session_id'] }}
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ (string) ($mediaEntry['view_url'] ?? '#') }}" target="_blank" class="crm-btn crm-btn-outline h-7 px-2 text-[11px]">Xem</a>
                                    <a href="{{ (string) ($mediaEntry['download_url'] ?? '#') }}" target="_blank" class="crm-btn crm-btn-outline h-7 px-2 text-[11px]">Tải</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
