@props([
    'conversation',
    'handoffPanel',
])

<form wire:submit="saveHandoffNote" class="grid gap-4 rounded-[1.75rem] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950/60 xl:grid-cols-[minmax(0,1.2fr)_13rem_13rem]">
    <div class="space-y-3">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $handoffPanel['summary_heading'] }}</h4>
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $conversation->handoffStatusBadgeClasses() }}">
                    {{ $conversation->handoffStatusLabel() }}
                </span>
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $conversation->handoffPriorityBadgeClasses() }}">
                    {{ $conversation->handoffPriorityLabel() }}
                </span>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $handoffPanel['summary_description'] }}
            </p>

            @if($handoffPanel['updated_at_text'])
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Cập nhật gần nhất bởi {{ $handoffPanel['updated_by_name'] }}
                    lúc {{ $handoffPanel['updated_at_text'] }}
                </p>
            @endif
        </div>

        <div class="space-y-2">
            <label for="handoff-summary" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ $handoffPanel['summary_label'] }}
            </label>
            <textarea
                id="handoff-summary"
                wire:model.live="handoffForm.summary"
                rows="3"
                placeholder="{{ $handoffPanel['summary_placeholder'] }}"
                class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                data-testid="handoff-summary"
            ></textarea>
            @error('handoffForm.summary')
                <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="space-y-4">
        <div class="space-y-2">
            <label for="handoff-status" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ $handoffPanel['status_label'] }}
            </label>
            <select
                id="handoff-status"
                wire:model.live="handoffForm.status"
                class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                data-testid="handoff-status"
            >
                @foreach($handoffPanel['status_options'] as $statusValue => $statusLabel)
                    <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                @endforeach
            </select>
            @error('handoffForm.status')
                <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="handoff-next-action-at" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ $handoffPanel['next_action_label'] }}
            </label>
            <input
                id="handoff-next-action-at"
                type="datetime-local"
                wire:model.live="handoffForm.next_action_at"
                class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                data-testid="handoff-next-action-at"
            />
            @error('handoffForm.next_action_at')
                <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="space-y-4">
        <div class="space-y-2">
            <label for="handoff-priority" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ $handoffPanel['priority_label'] }}
            </label>
            <select
                id="handoff-priority"
                wire:model.live="handoffForm.priority"
                class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                data-testid="handoff-priority"
            >
                @foreach($handoffPanel['priority_options'] as $priorityValue => $priorityLabel)
                    <option value="{{ $priorityValue }}">{{ $priorityLabel }}</option>
                @endforeach
            </select>
            @error('handoffForm.priority')
                <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex h-full flex-col justify-between gap-3 rounded-2xl border border-dashed border-gray-200 bg-gray-50/80 p-4 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900/60 dark:text-gray-400">
            <p>{{ $handoffPanel['guidance'] }}</p>

            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveHandoffNote" data-testid="handoff-save">
                {{ $handoffPanel['submit_label'] }}
            </x-filament::button>
        </div>
    </div>
</form>
