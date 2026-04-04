<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800/50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Thời gian</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Người sửa</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Thiết lập</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Giá trị cũ</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Giá trị mới</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
            @forelse($auditLog['items'] as $log)
                <tr>
                    <td class="px-3 py-2 whitespace-nowrap text-gray-600 dark:text-gray-300">
                        {{ $log['changed_at_label'] }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-gray-800 dark:text-gray-100">
                        {{ $log['changed_by_name'] }}
                    </td>
                    <td class="px-3 py-2 text-gray-800 dark:text-gray-100">
                        <div class="font-medium">{{ $log['setting_label'] }}</div>
                        <div class="text-xs text-gray-500">{{ $log['setting_key'] }}</div>

                        @if(filled($log['change_reason'] ?? null))
                            <div class="text-xs text-warning-700 dark:text-warning-300">{{ $log['change_reason'] }}</div>
                        @endif

                        @if(filled($log['grace_expires_at_label'] ?? null))
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Grace tới {{ $log['grace_expires_at_label'] }}
                            </div>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $log['old_value'] }}</td>
                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $log['new_value'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                        {{ $auditLog['empty_state_text'] }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
