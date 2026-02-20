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
}" class="tooth-selector">

    {{-- Legend --}}
    <div class="flex justify-center gap-6 mb-4 text-xs">
        <div class="flex items-center gap-2">
            <span class="w-4 h-4 rounded bg-white border-2 border-primary-500"></span>
            <span>Đang chọn</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-4 h-4 rounded bg-white border border-gray-300"></span>
            <span>Bình thường</span>
        </div>
    </div>

    {{-- Chart --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">

        {{-- Adult Upper --}}
        <div class="flex justify-center gap-1 mb-2">
            @foreach(['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'] as $tooth)
                <button type="button" @click="toggleTooth('{{ $tooth }}')"
                    :class="isSelected('{{ $tooth }}') ? 'bg-primary-50 border-primary-500 text-primary-700' : 'bg-white border-gray-300 text-gray-500 hover:border-gray-400'"
                    class="w-8 h-10 rounded border-2 text-xs font-medium transition-all">
                    {{ $tooth }}
                </button>
            @endforeach
        </div>

        {{-- Child Upper --}}
        <div class="flex justify-center gap-1 mb-4">
            @foreach(['55', '54', '53', '52', '51', '61', '62', '63', '64', '65'] as $tooth)
                <button type="button" @click="toggleTooth('{{ $tooth }}')"
                    :class="isSelected('{{ $tooth }}') ? 'bg-primary-50 border-primary-500 text-primary-700' : 'bg-white border-gray-300 text-gray-500 hover:border-gray-400'"
                    class="w-6 h-8 rounded border-2 text-[10px] font-medium transition-all opacity-80">
                    {{ $tooth }}
                </button>
            @endforeach
        </div>

        <div class="border-t border-dashed border-gray-300 my-2"></div>

        {{-- Child Lower --}}
        <div class="flex justify-center gap-1 mb-2">
            @foreach(['85', '84', '83', '82', '81', '71', '72', '73', '74', '75'] as $tooth)
                <button type="button" @click="toggleTooth('{{ $tooth }}')"
                    :class="isSelected('{{ $tooth }}') ? 'bg-primary-50 border-primary-500 text-primary-700' : 'bg-white border-gray-300 text-gray-500 hover:border-gray-400'"
                    class="w-6 h-8 rounded border-2 text-[10px] font-medium transition-all opacity-80">
                    {{ $tooth }}
                </button>
            @endforeach
        </div>

        {{-- Adult Lower --}}
        <div class="flex justify-center gap-1">
            @foreach(['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'] as $tooth)
                <button type="button" @click="toggleTooth('{{ $tooth }}')"
                    :class="isSelected('{{ $tooth }}') ? 'bg-primary-50 border-primary-500 text-primary-700' : 'bg-white border-gray-300 text-gray-500 hover:border-gray-400'"
                    class="w-8 h-10 rounded border-2 text-xs font-medium transition-all">
                    {{ $tooth }}
                </button>
            @endforeach
        </div>

    </div>

    <div class="mt-2 text-center text-sm text-gray-600">
        Đã chọn: <span x-text="state?.join(', ') || 'Chưa chọn'"></span>
    </div>
</div>