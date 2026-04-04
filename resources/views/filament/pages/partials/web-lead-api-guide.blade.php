<div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
    <div class="mb-2 text-sm font-semibold">Hướng dẫn tích hợp Web Lead API</div>
    <ul class="list-disc space-y-1 pl-5 text-xs md:text-sm">
        <li>Endpoint: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">{{ route('api.v1.web-leads.store') }}</code></li>
        <li>Method: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">POST</code></li>
        <li>Headers bắt buộc: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">Authorization: Bearer &lt;TOKEN&gt;</code>, <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">X-Idempotency-Key: &lt;UNIQUE_KEY&gt;</code></li>
        <li>Payload tối thiểu: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">full_name</code>, <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">phone</code>. Tùy chọn: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">branch_code</code>, <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">note</code>.</li>
    </ul>

    <div class="mt-3 overflow-x-auto rounded-md border border-gray-200 bg-white p-3 text-[11px] leading-5 text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200">
        <pre class="min-w-[620px]"><code>curl -X POST '{{ route('api.v1.web-leads.store') }}' \
  -H 'Authorization: Bearer &lt;TOKEN&gt;' \
  -H 'X-Idempotency-Key: web-{{ now()->format('YmdHis') }}-001' \
  -H 'Content-Type: application/json' \
  -d '{
    "full_name": "Nguyen Van A",
    "phone": "0901234567",
    "branch_code": "BR-WEB-HCM",
    "note": "Form tu website landing page"
  }'</code></pre>
    </div>
</div>
