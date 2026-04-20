@props([
    'panel',
])

<section
    class="mb-4 space-y-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60"
    aria-labelledby="{{ $panel['labelled_by'] }}"
    aria-describedby="{{ $panel['described_by'] }}"
>
    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
        <div class="space-y-1">
            <h2 id="{{ $panel['labelled_by'] }}" class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ $panel['heading'] }}
            </h2>
            <p id="{{ $panel['described_by'] }}" class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                {{ $panel['description'] }}
            </p>
        </div>

        <p class="rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-950/40 dark:text-primary-300 dark:ring-primary-400/20" aria-live="polite">
            {{ $panel['active_summary_label'] }}:
            <span x-text="activeFilterSummary()"></span>
        </p>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="space-y-1">
            <label for="{{ $panel['status_filter']['id'] }}" class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ $panel['status_filter']['label'] }}</label>
            <select id="{{ $panel['status_filter']['id'] }}" x-model="filters.status" @change="applyFilters()" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                @foreach($panel['status_options'] as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1">
            <label for="{{ $panel['branch_filter']['id'] }}" class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ $panel['branch_filter']['label'] }}</label>
            <select id="{{ $panel['branch_filter']['id'] }}" x-model="filters.branchId" @change="applyFilters()" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                @foreach($panel['branch_options'] as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1">
            <label for="{{ $panel['doctor_filter']['id'] }}" class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ $panel['doctor_filter']['label'] }}</label>
            <select id="{{ $panel['doctor_filter']['id'] }}" x-model="filters.doctorId" @change="applyFilters()" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                @foreach($panel['doctor_options'] as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end">
            <button
                type="button"
                @click="resetFilters()"
                aria-label="{{ $panel['reset_label'] }}"
                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ $panel['reset_label'] }}
            </button>
        </div>
    </div>
</section>
