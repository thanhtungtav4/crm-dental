@php
    $roleOptions = (array) ($field['options'] ?? []);
@endphp

<div class="md:col-span-2">
    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
        {{ $field['label'] }}
    </label>

    @if($roleOptions !== [])
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($roleOptions as $roleValue => $roleLabel)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    <input
                        type="checkbox"
                        value="{{ $roleValue }}"
                        wire:model.live="{{ $statePath }}"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span>{{ $roleLabel }}</span>
                </label>
            @endforeach
        </div>
    @else
        <p class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Chưa có role để cấu hình. Vui lòng seed role trước khi bật thông báo realtime.
        </p>
    @endif

    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        @if(($field['key'] ?? null) === 'web_lead.realtime_notification_roles')
            Chỉ các user thuộc nhóm quyền đã chọn mới nhận thông báo realtime khi có lead mới từ website.
        @elseif(($field['key'] ?? null) === 'web_lead.internal_email_recipient_roles')
            Các user thuộc nhóm quyền đã chọn sẽ nhận email nội bộ nếu có email hợp lệ và được phép truy cập chi nhánh của lead.
        @elseif(($field['key'] ?? null) === 'popup.sender_roles')
            Chỉ các role đã chọn mới có quyền gửi popup toàn hệ thống.
        @elseif(($field['key'] ?? null) === 'security.mfa_required_roles')
            User thuộc các role này sẽ bắt buộc cấu hình MFA khi đăng nhập admin.
        @else
            Cấu hình nhóm quyền áp dụng cho runtime tương ứng.
        @endif
    </p>
    @error($statePath)
        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
    @enderror
</div>
