<x-mail::message>
# Web lead mới từ website

Một lead mới đã vào CRM và đang chờ đội phụ trách xử lý.

- Họ tên: **{{ data_get($payload, 'customer_name', 'Chưa có tên') }}**
- Điện thoại: **{{ data_get($payload, 'customer_phone', 'Chưa có số') }}**
- Chi nhánh: **{{ data_get($payload, 'branch_name', 'Chưa xác định') }}**
- Request ID: `{{ data_get($payload, 'request_id', '-') }}`
- Trạng thái ingest: **{{ strtoupper((string) data_get($payload, 'ingestion_status', 'created')) }}**

@if(filled(data_get($payload, 'note')))
Ghi chú web:

{{ data_get($payload, 'note') }}
@endif

@if(filled(data_get($payload, 'customer_url')))
<x-mail::button :url="data_get($payload, 'customer_url')">
Mở lead trong CRM
</x-mail::button>
@endif

@if(filled(data_get($payload, 'frontdesk_url')))
<x-mail::button :url="data_get($payload, 'frontdesk_url')" color="success">
Mở Frontdesk Control Center
</x-mail::button>
@endif

Người nhận: {{ $delivery->resolvedRecipientLabel() }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
