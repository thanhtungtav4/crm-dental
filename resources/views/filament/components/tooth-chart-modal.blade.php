<div class="tooth-chart-modal p-4">
    {{-- Legend --}}
    <div class="flex justify-center gap-6 mb-6 text-sm">
        <div class="flex items-center gap-2">
            <span class="w-4 h-4 rounded bg-gray-400"></span>
            <span>Tình trạng hiện tại</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-4 h-4 rounded bg-red-500"></span>
            <span>Đang điều trị</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-4 h-4 rounded bg-green-500"></span>
            <span>Hoàn thành điều trị</span>
        </div>
    </div>

    {{-- Tooth Chart Container --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">

        {{-- Row 1: Adult Upper (18-28) --}}
        <div class="mb-2">
            <div class="text-xs text-gray-500 text-center mb-1">Hàm trên người lớn</div>
            <div class="flex justify-center gap-1">
                @foreach(['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'] as $tooth)
                    @php
                        $conditions = $toothConditions->where('tooth_number', $tooth);
                        
                        $statusColor = 'bg-white border-gray-300 text-gray-500 hover:border-blue-400';
                        $tooltipText = "Răng $tooth";
                        $displayCode = '';
                        $codeColor = '#666';

                        if ($conditions->isNotEmpty()) {
                            if ($conditions->contains('treatment_status', 'in_treatment')) {
                                $statusColor = 'bg-red-100 border-red-500 text-red-700';
                            } elseif ($conditions->contains('treatment_status', 'completed')) {
                                $statusColor = 'bg-green-100 border-green-500 text-green-700';
                            } elseif ($conditions->contains('treatment_status', 'current')) {
                                $statusColor = 'bg-gray-100 border-gray-400 text-gray-700';
                            }

                            $conditionNames = $conditions->map(fn($c) => $c->condition?->name)->filter()->join(', ');
                            $tooltipText .= $conditionNames ? " - $conditionNames" : '';

                            $firstCondition = $conditions->first();
                            $displayCode = $conditions->count() > 1 ? '*' . $conditions->count() : ($firstCondition->condition?->code ?? '');
                            $codeColor = $firstCondition->condition?->color ?? '#666';
                        }
                    @endphp
                    <button type="button"
                        class="tooth-btn w-10 h-12 rounded border-2 {{ $statusColor }} text-xs font-medium transition-all hover:scale-105 relative"
                        data-tooth="{{ $tooth }}"
                        title="{{ $tooltipText }}">
                        <span class="block">{{ $tooth }}</span>
                        @if($conditions->isNotEmpty())
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[8px] font-bold"
                                style="color: {{ $codeColor }}">
                                {{ $displayCode }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Row 2: Child Upper (55-65) --}}
        <div class="mb-4">
            <div class="text-xs text-gray-500 text-center mb-1">Răng sữa trên</div>
            <div class="flex justify-center gap-1">
                <div class="w-20"></div> {{-- Spacer --}}
                @foreach(['55', '54', '53', '52', '51', '61', '62', '63', '64', '65'] as $tooth)
                    @php
                        $conditions = $toothConditions->where('tooth_number', $tooth);
                        
                        $statusColor = 'bg-white border-gray-300 text-gray-400 hover:border-blue-400';
                        $tooltipText = "Răng sữa $tooth";
                        $displayCode = '';
                        $codeColor = '#666';

                        if ($conditions->isNotEmpty()) {
                            if ($conditions->contains('treatment_status', 'in_treatment')) {
                                $statusColor = 'bg-red-100 border-red-500 text-red-700';
                            } elseif ($conditions->contains('treatment_status', 'completed')) {
                                $statusColor = 'bg-green-100 border-green-500 text-green-700';
                            } elseif ($conditions->contains('treatment_status', 'current')) {
                                $statusColor = 'bg-gray-100 border-gray-400 text-gray-700';
                            }

                            $conditionNames = $conditions->map(fn($c) => $c->condition?->name)->filter()->join(', ');
                            $tooltipText .= $conditionNames ? " - $conditionNames" : '';

                            $firstCondition = $conditions->first();
                            $displayCode = $conditions->count() > 1 ? '*' . $conditions->count() : ($firstCondition->condition?->code ?? '');
                            $codeColor = $firstCondition->condition?->color ?? '#666';
                        }
                    @endphp
                    <button type="button"
                        class="tooth-btn w-8 h-10 rounded border-2 {{ $statusColor }} text-[10px] font-medium transition-all hover:scale-105 relative"
                        data-tooth="{{ $tooth }}"
                        title="{{ $tooltipText }}">
                        <span class="block">{{ $tooth }}</span>
                        @if($conditions->isNotEmpty())
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[6px] font-bold"
                                style="color: {{ $codeColor }}">
                                {{ $displayCode }}
                            </span>
                        @endif
                    </button>
                @endforeach
                <div class="w-20"></div> {{-- Spacer --}}
            </div>
        </div>

        {{-- Divider --}}
        <div class="border-t-2 border-dashed border-gray-300 my-4"></div>

        {{-- Row 3: Child Lower (85-75) --}}
        <div class="mb-2">
            <div class="text-xs text-gray-500 text-center mb-1">Răng sữa dưới</div>
            <div class="flex justify-center gap-1">
                <div class="w-20"></div> {{-- Spacer --}}
                @foreach(['85', '84', '83', '82', '81', '71', '72', '73', '74', '75'] as $tooth)
                    @php
                        $conditions = $toothConditions->where('tooth_number', $tooth);
                        
                        $statusColor = 'bg-white border-gray-300 text-gray-400 hover:border-blue-400';
                        $tooltipText = "Răng sữa $tooth";
                        $displayCode = '';
                        $codeColor = '#666';

                        if ($conditions->isNotEmpty()) {
                            if ($conditions->contains('treatment_status', 'in_treatment')) {
                                $statusColor = 'bg-red-100 border-red-500 text-red-700';
                            } elseif ($conditions->contains('treatment_status', 'completed')) {
                                $statusColor = 'bg-green-100 border-green-500 text-green-700';
                            } elseif ($conditions->contains('treatment_status', 'current')) {
                                $statusColor = 'bg-gray-100 border-gray-400 text-gray-700';
                            }

                            $conditionNames = $conditions->map(fn($c) => $c->condition?->name)->filter()->join(', ');
                            $tooltipText .= $conditionNames ? " - $conditionNames" : '';

                            $firstCondition = $conditions->first();
                            $displayCode = $conditions->count() > 1 ? '*' . $conditions->count() : ($firstCondition->condition?->code ?? '');
                            $codeColor = $firstCondition->condition?->color ?? '#666';
                        }
                    @endphp
                    <button type="button"
                        class="tooth-btn w-8 h-10 rounded border-2 {{ $statusColor }} text-[10px] font-medium transition-all hover:scale-105 relative"
                        data-tooth="{{ $tooth }}"
                        title="{{ $tooltipText }}">
                        <span class="block">{{ $tooth }}</span>
                        @if($conditions->isNotEmpty())
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[6px] font-bold"
                                style="color: {{ $codeColor }}">
                                {{ $displayCode }}
                            </span>
                        @endif
                    </button>
                @endforeach
                <div class="w-20"></div> {{-- Spacer --}}
            </div>
        </div>

        {{-- Row 4: Adult Lower (48-38) --}}
        <div>
            <div class="text-xs text-gray-500 text-center mb-1">Hàm dưới người lớn</div>
            <div class="flex justify-center gap-1">
                @foreach(['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'] as $tooth)
                    @php
                        $conditions = $toothConditions->where('tooth_number', $tooth);

                        // Determine status priority: in_treatment > completed > current
                        $statusColor = 'bg-white border-gray-300 text-gray-500 hover:border-blue-400';
                        $tooltipText = "Răng $tooth";
                        $displayCode = '';
                        $codeColor = '#666';

                        if ($conditions->isNotEmpty()) {
                            // Status Logic
                            if ($conditions->contains('treatment_status', 'in_treatment')) {
                                $statusColor = 'bg-red-100 border-red-500 text-red-700';
                            } elseif ($conditions->contains('treatment_status', 'completed')) {
                                $statusColor = 'bg-green-100 border-green-500 text-green-700';
                            } elseif ($conditions->contains('treatment_status', 'current')) {
                                $statusColor = 'bg-gray-100 border-gray-400 text-gray-700';
                            }

                            // Tooltip Logic
                            $conditionNames = $conditions->map(fn($c) => $c->condition?->name)->filter()->join(', ');
                            $tooltipText .= $conditionNames ? " - $conditionNames" : '';

                            // Badge Logic (Show first code or special indicator)
                            $firstCondition = $conditions->first();
                            $displayCode = $conditions->count() > 1 ? '*' . $conditions->count() : ($firstCondition->condition?->code ?? '');
                            $codeColor = $firstCondition->condition?->color ?? '#666';
                        }
                    @endphp
                    <button type="button"
                        class="tooth-btn w-10 h-12 rounded border-2 {{ $statusColor }} text-xs font-medium transition-all hover:scale-105 relative"
                        data-tooth="{{ $tooth }}" title="{{ $tooltipText }}">
                        <span class="block">{{ $tooth }}</span>
                        @if($conditions->isNotEmpty())
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[8px] font-bold"
                                style="color: {{ $codeColor }}">
                                {{ $displayCode }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Condition Summary --}}
    @if($toothConditions->isNotEmpty())
        <div class="mt-6">
            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Tình trạng răng hiện tại</h4>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach($toothConditions->groupBy('condition.name') as $conditionName => $conditions)
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                        <div class="font-medium text-sm" style="color: {{ $conditions->first()->condition?->color ?? '#666' }}">
                            {{ $conditionName ?: 'Không xác định' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Răng: {{ $conditions->pluck('tooth_number')->join(', ') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <p class="text-center text-xs text-gray-400 mt-4">
        Click vào răng để xem chi tiết hoặc thêm tình trạng mới từ bảng bên trên.
    </p>
</div>