<div class="md:col-span-2">
    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
        {{ $field['label'] }}
    </label>
    <x-filament::input.wrapper>
        <textarea
            wire:model.blur="{{ $statePath }}"
            rows="4"
            class="fi-input min-h-28"
        ></textarea>
    </x-filament::input.wrapper>
    @if(($field['key'] ?? null) === 'web_lead.internal_email_recipient_emails')
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Dùng để thêm mailbox cố định của bộ phận như `lead@clinic.vn`. Mỗi dòng một email, hệ thống sẽ tự loại trùng với email user theo role.
        </p>
    @endif
    @error($statePath)
        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
    @enderror
</div>
