<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payment->direction === 'refund' ? 'Phiếu hoàn' : 'Phiếu thu' }} #{{ $payment->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; margin: 24px; color: #111827; }
        h1, h2, p { margin: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin-bottom: 12px; }
        .row { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 6px; }
        .label { color: #6b7280; }
        .value { font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $payment->direction === 'refund' ? 'PHIẾU HOÀN TIỀN' : 'PHIẾU THU TIỀN' }}</h1>
        <p>{{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="card">
        <div class="row"><span class="label">Mã phiếu</span><span class="value">#{{ $payment->id }}</span></div>
        <div class="row"><span class="label">Hóa đơn</span><span class="value">{{ $payment->invoice?->invoice_no ?? '-' }}</span></div>
        <div class="row"><span class="label">Bệnh nhân</span><span class="value">{{ $payment->invoice?->patient?->full_name ?? '-' }}</span></div>
        <div class="row"><span class="label">Mã hồ sơ</span><span class="value">{{ $payment->invoice?->patient?->patient_code ?? '-' }}</span></div>
        <div class="row"><span class="label">Ngày lập phiếu</span><span class="value">{{ optional($payment->paid_at)->format('d/m/Y H:i') ?? '-' }}</span></div>
        <div class="row"><span class="label">Phương thức</span><span class="value">{{ $payment->getMethodLabel() }}</span></div>
        <div class="row"><span class="label">Người nhận</span><span class="value">{{ $payment->receiver?->name ?? '-' }}</span></div>
        <div class="row"><span class="label">Mã giao dịch</span><span class="value">{{ $payment->transaction_ref ?: '-' }}</span></div>
        <div class="row"><span class="label">Số tiền</span><span class="value">{{ number_format((float) $payment->amount, 0, ',', '.') }}đ</span></div>
        <div class="row"><span class="label">Nội dung</span><span class="value">{{ $payment->note ?: '-' }}</span></div>
        @if($payment->direction === 'refund')
            <div class="row"><span class="label">Lý do hoàn</span><span class="value">{{ $payment->refund_reason ?: '-' }}</span></div>
        @endif
    </div>

    @if(!($isPdf ?? false))
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>
