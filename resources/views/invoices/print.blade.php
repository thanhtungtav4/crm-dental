<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn {{ $invoice->invoice_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 24px; color: #111827; }
        h1, h2, p { margin: 0; }
        .header { display: flex; justify-content: space-between; margin-bottom: 16px; }
        .badge { display: inline-block; padding: 2px 8px; border: 1px solid #d1d5db; border-radius: 999px; font-size: 11px; }
        .section { margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>PHÒNG KHÁM NHA KHOA</h1>
            <p class="muted">Hóa đơn điều trị</p>
        </div>
        <div class="right">
            <h2>#{{ $invoice->invoice_no }}</h2>
            <p class="muted">{{ optional($invoice->issued_at)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</p>
            <span class="badge">{{ strtoupper($invoice->status) }}</span>
        </div>
    </div>

    <div class="section">
        <table>
            <tr>
                <th>Bệnh nhân</th>
                <td>{{ $invoice->patient?->full_name ?? '-' }}</td>
                <th>Mã hồ sơ</th>
                <td>{{ $invoice->patient?->patient_code ?? '-' }}</td>
            </tr>
            <tr>
                <th>Kế hoạch điều trị</th>
                <td>{{ $invoice->plan?->title ?? '-' }}</td>
                <th>Ngày đến hạn</th>
                <td>{{ optional($invoice->due_date)->format('d/m/Y') ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>Chỉ tiêu</th>
                    <th class="right">Số tiền</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Tạm tính</td>
                    <td class="right">{{ number_format((float) $invoice->subtotal, 0, ',', '.') }}đ</td>
                </tr>
                <tr>
                    <td>Giảm giá</td>
                    <td class="right">{{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}đ</td>
                </tr>
                <tr>
                    <td>Thuế/Phụ phí</td>
                    <td class="right">{{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}đ</td>
                </tr>
                <tr>
                    <th>Tổng thanh toán</th>
                    <th class="right">{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}đ</th>
                </tr>
                <tr>
                    <td>Đã thu (net)</td>
                    <td class="right">{{ number_format((float) $invoice->getTotalPaid(), 0, ',', '.') }}đ</td>
                </tr>
                <tr>
                    <th>Còn lại</th>
                    <th class="right">{{ number_format((float) $invoice->calculateBalance(), 0, ',', '.') }}đ</th>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Lịch sử thu/hoàn</h2>
        <table>
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Loại</th>
                    <th>Phương thức</th>
                    <th>Người nhận</th>
                    <th>Nội dung</th>
                    <th class="right">Số tiền</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->payments as $payment)
                    <tr>
                        <td>{{ optional($payment->paid_at)->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>{{ $payment->getDirectionLabel() }}</td>
                        <td>{{ $payment->getMethodLabel() }}</td>
                        <td>{{ $payment->receiver?->name ?? '-' }}</td>
                        <td>{{ $payment->note ?: ($payment->refund_reason ?: '-') }}</td>
                        <td class="right">{{ number_format((float) $payment->amount, 0, ',', '.') }}đ</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="right muted">Chưa có giao dịch</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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
