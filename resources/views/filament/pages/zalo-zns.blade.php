<x-filament::section>
    @php($summary = $this->operationalSummary)

    <div class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60">
                <p class="text-xs text-gray-500">Automation chờ xử lý</p>
                <p class="text-xl font-semibold">{{ number_format($summary['automation_pending']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                <p class="text-xs text-amber-700 dark:text-amber-300">Automation retry tới hạn</p>
                <p class="text-xl font-semibold text-amber-700 dark:text-amber-300">{{ number_format($summary['automation_retry_due']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                <p class="text-xs text-rose-700 dark:text-rose-300">Automation dead-letter</p>
                <p class="text-xl font-semibold text-rose-700 dark:text-rose-300">{{ number_format($summary['automation_dead']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-400/30 dark:bg-blue-500/10">
                <p class="text-xs text-blue-700 dark:text-blue-300">Campaign đang chạy</p>
                <p class="text-xl font-semibold text-blue-700 dark:text-blue-300">{{ number_format($summary['campaigns_running']) }}</p>
            </div>
            <div class="rounded-xl border border-orange-200 bg-orange-50 p-3 dark:border-orange-400/30 dark:bg-orange-500/10">
                <p class="text-xs text-orange-700 dark:text-orange-300">Delivery retry tới hạn</p>
                <p class="text-xl font-semibold text-orange-700 dark:text-orange-300">{{ number_format($summary['deliveries_retry_due']) }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-400/30 dark:bg-red-500/10">
                <p class="text-xs text-red-700 dark:text-red-300">Delivery terminal lỗi</p>
                <p class="text-xl font-semibold text-red-700 dark:text-red-300">{{ number_format($summary['deliveries_terminal_failed']) }}</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-3 dark:border-violet-400/30 dark:bg-violet-500/10">
                <p class="text-xs text-violet-700 dark:text-violet-300">Campaign failed</p>
                <p class="text-xl font-semibold text-violet-700 dark:text-violet-300">{{ number_format($summary['campaigns_failed']) }}</p>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-[1.4fr,1fr]">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/60">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Triage nhanh</h3>
                <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <p>Trang này ưu tiên backlog cần xử lý: retry tới hạn, dead-letter, processing kẹt và failed campaign.</p>
                    <p>Thao tác đổi trạng thái campaign vẫn đi qua workflow action trong resource campaign, không sửa tay trực tiếp trên page này.</p>
                    <p>Dùng filter theo luồng, mã provider và chi nhánh để khoanh vùng lỗi trước khi mở campaign tương ứng.</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/60">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Gợi ý xử lý</h3>
                <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <p><span class="font-medium text-amber-700 dark:text-amber-300">Retry tới hạn:</span> kiểm tra lỗi provider và template trước khi để scheduler chạy lại.</p>
                    <p><span class="font-medium text-rose-700 dark:text-rose-300">Dead-letter:</span> ưu tiên root cause, tránh bấm chạy lại campaign khi lỗi đến từ dữ liệu template hoặc số điện thoại.</p>
                    <p><span class="font-medium text-blue-700 dark:text-blue-300">Campaign running:</span> nếu backlog tăng mà không giảm, mở campaign để kiểm tra deliveries relation manager.</p>
                </div>
            </div>
        </div>

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament::section>
