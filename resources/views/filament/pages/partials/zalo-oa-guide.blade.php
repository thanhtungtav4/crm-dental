<div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
    <div class="mb-2 text-sm font-semibold">Checklist triển khai Zalo OA</div>
    <ul class="list-disc space-y-1 pl-5 text-xs md:text-sm">
        <li>Khai báo đầy đủ OA ID, App ID, App Secret, Webhook Verify Token.</li>
        <li>Webhook URL: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">{{ route('api.v1.integrations.zalo.webhook') }}</code></li>
        <li>Luôn dùng token webhook ngẫu nhiên dài tối thiểu 24 ký tự.</li>
        <li>Chỉ bật tích hợp khi OA đã đăng ký callback qua HTTPS.</li>
    </ul>
</div>
