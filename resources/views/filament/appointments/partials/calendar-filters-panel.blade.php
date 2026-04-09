@props([
    'panel',
])

<div class="mb-4 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 md:grid-cols-4 dark:border-gray-700 dark:bg-gray-900/60">
    <div>
        <label class="mb-1 block text-xs text-gray-500">Trạng thái</label>
        <select x-model="filters.status" @change="applyFilters()" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
            @foreach($panel['status_options'] as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs text-gray-500">Chi nhánh</label>
        <select x-model="filters.branchId" @change="applyFilters()" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
            @foreach($panel['branch_options'] as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs text-gray-500">Bác sĩ</label>
        <select x-model="filters.doctorId" @change="applyFilters()" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
            @foreach($panel['doctor_options'] as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end">
        <button type="button" @click="resetFilters()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
            Đặt lại bộ lọc
        </button>
    </div>
</div>
