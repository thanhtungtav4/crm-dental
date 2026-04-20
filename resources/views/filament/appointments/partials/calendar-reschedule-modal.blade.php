@props([
    'panel',
])

<x-filament::modal
    :id="$panel['id']"
    width="2xl"
    :heading="$panel['heading']"
    :description="$panel['description']"
>
    <div class="space-y-4">
        <div class="grid gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-2 dark:border-gray-800 dark:bg-gray-900/70">
            <div class="space-y-1">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $panel['appointment_label'] }}</p>
                <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="rescheduleDialog.appointmentTitle || @js($panel['empty_appointment_title'])"></p>
            </div>

            <div class="space-y-1">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $panel['start_label'] }}</p>
                <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="rescheduleDialog.startLabel || @js($panel['empty_start_label'])"></p>
            </div>

            <div class="space-y-1" x-show="rescheduleDialog.doctorLabel">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $panel['doctor_label'] }}</p>
                <p class="text-sm text-gray-700 dark:text-gray-200" x-text="rescheduleDialog.doctorLabel"></p>
            </div>

            <div class="space-y-1" x-show="rescheduleDialog.branchLabel">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $panel['branch_label'] }}</p>
                <p class="text-sm text-gray-700 dark:text-gray-200" x-text="rescheduleDialog.branchLabel"></p>
            </div>
        </div>

        <div
            x-cloak
            x-show="rescheduleDialog.conflictMessage"
            class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-100"
            role="alert"
            aria-live="assertive"
            aria-describedby="{{ $panel['conflict_message_id'] }} {{ $panel['conflict_note_id'] }}"
        >
            <p class="font-semibold">{{ $panel['conflict_heading'] }}</p>
            <p id="{{ $panel['conflict_message_id'] }}" class="mt-1" x-text="rescheduleDialog.conflictMessage"></p>
            <p id="{{ $panel['conflict_note_id'] }}" class="mt-2 text-xs text-warning-800/90 dark:text-warning-200/80">
                {{ $panel['conflict_note'] }}
            </p>
        </div>

        <div class="space-y-2">
            <label for="calendar-reschedule-reason" class="block text-sm font-medium text-gray-900 dark:text-white">
                {{ $panel['reason_label'] }}
            </label>
            <textarea
                id="calendar-reschedule-reason"
                x-model="rescheduleDialog.reason"
                x-on:input="rescheduleDialog.errorMessage = ''"
                x-bind:aria-invalid="rescheduleDialog.errorMessage ? 'true' : 'false'"
                rows="4"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                :placeholder="@js($panel['reason_placeholder'])"
                aria-describedby="{{ $panel['reason_help_id'] }} {{ $panel['reason_error_id'] }}"
            ></textarea>
            <p id="{{ $panel['reason_help_id'] }}" class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                {{ $panel['reason_help'] }}
            </p>
            <p
                id="{{ $panel['reason_error_id'] }}"
                x-cloak
                x-show="rescheduleDialog.errorMessage"
                class="text-sm text-danger-600 dark:text-danger-400"
                role="alert"
                aria-live="polite"
                x-text="rescheduleDialog.errorMessage"
            ></p>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
                type="button"
                class="crm-btn crm-btn-outline crm-btn-md"
                x-bind:disabled="rescheduleDialog.isSubmitting"
                x-bind:aria-disabled="rescheduleDialog.isSubmitting.toString()"
                x-on:click="closeRescheduleDialog()"
            >
                {{ $panel['cancel_label'] }}
            </button>

            <button
                type="button"
                class="crm-btn crm-btn-primary crm-btn-md"
                x-bind:disabled="rescheduleDialog.isSubmitting"
                x-bind:aria-disabled="rescheduleDialog.isSubmitting.toString()"
                x-bind:aria-busy="rescheduleDialog.isSubmitting.toString()"
                x-on:click="submitReschedule()"
            >
                <span x-show="!rescheduleDialog.isSubmitting" x-text="rescheduleDialog.force ? @js($panel['submit_override_label']) : @js($panel['submit_label'])"></span>
                <span x-cloak x-show="rescheduleDialog.isSubmitting">{{ $panel['submitting_label'] }}</span>
            </button>
        </div>
    </x-slot>
</x-filament::modal>
