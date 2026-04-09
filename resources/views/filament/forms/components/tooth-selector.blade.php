<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{
        state: $wire.$entangle('{{ $getStatePath() }}'),
        toggleTooth(tooth) {
            if (!this.state) this.state = [];
            if (this.state.includes(tooth)) {
                this.state = this.state.filter(t => t !== tooth);
            } else {
                this.state.push(tooth);
            }
        },
        isSelected(tooth) {
            return this.state && this.state.includes(tooth);
        }
    }" class="tooth-selector text-gray-700 dark:text-gray-200">

        <div class="mb-4 flex justify-center gap-6 text-xs">
            @foreach($legendItems as $legendItem)
                <div class="flex items-center gap-2">
                    <span class="{{ $legendItem['swatch_classes'] }}"></span>
                    <span>{{ $legendItem['label'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            @foreach($selectorRows as $row)
                <div class="{{ $row['row_classes'] }}">
                    @foreach($row['teeth'] as $tooth)
                        <button type="button" @click="toggleTooth('{{ $tooth }}')"
                            :class="isSelected('{{ $tooth }}') ? '{{ $selectedButtonClasses }}' : '{{ $defaultButtonClasses }}'"
                            class="{{ $row['button_classes'] }}">
                            {{ $tooth }}
                        </button>
                    @endforeach
                </div>

                @if($row['divider_after'])
                    <div class="my-2 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                @endif
            @endforeach
        </div>

        <div class="mt-2 text-center text-sm text-gray-600 dark:text-gray-300">
            Đã chọn: <span x-text="state?.join(', ') || '{{ $emptySelectionLabel }}'"></span>
        </div>
    </div>
</x-dynamic-component>
