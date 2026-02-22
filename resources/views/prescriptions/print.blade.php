<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn thuốc {{ $prescription->prescription_code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 11px;
            color: #666;
        }

        .patient-info {
            margin-bottom: 20px;
        }

        .patient-info table {
            width: 100%;
        }

        .patient-info td {
            padding: 3px 0;
        }

        .patient-info .label {
            font-weight: bold;
            width: 120px;
        }

        .prescription-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }

        .medications {
            margin-bottom: 30px;
        }

        .medications table {
            width: 100%;
            border-collapse: collapse;
        }

        .medications th,
        .medications td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .medications th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .medications .stt {
            width: 40px;
            text-align: center;
        }

        .medications .qty {
            width: 80px;
            text-align: center;
        }

        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .footer .left,
        .footer .right {
            width: 45%;
        }

        .footer .right {
            text-align: center;
        }

        .footer .date {
            font-style: italic;
            margin-bottom: 60px;
        }

        .footer .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
        }

        .notes {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 3px solid #007bff;
        }

        .notes strong {
            display: block;
            margin-bottom: 5px;
        }

        .medication-note-cell {
            font-style: italic;
            color: #666;
        }

        .medications-empty {
            text-align: center;
            color: #999;
        }

        .footer-notes-list {
            padding-left: 15px;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>PHÒNG KHÁM NHA KHOA</h1>
        <p>Địa chỉ: ...........................................</p>
        <p>Điện thoại: ................. | Email: .................</p>
    </div>

    <div class="prescription-title">ĐƠN THUỐC</div>

    <div class="patient-info">
        <table>
            <tr>
                <td class="label">Mã đơn thuốc:</td>
                <td>{{ $prescription->prescription_code }}</td>
                <td class="label">Ngày kê đơn:</td>
                <td>{{ $prescription->created_at?->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">Họ tên BN:</td>
                <td>{{ $prescription->patient?->full_name }}</td>
                <td class="label">Mã BN:</td>
                <td>{{ $prescription->patient?->patient_code }}</td>
            </tr>
            <tr>
                <td class="label">Ngày sinh:</td>
                <td>{{ $prescription->patient?->birthday?->format('d/m/Y') }}</td>
                <td class="label">Giới tính:</td>
                <td>{{ $prescription->patient?->getGenderLabel() }}</td>
            </tr>
            <tr>
                <td class="label">Địa chỉ:</td>
                <td colspan="3">{{ $prescription->patient?->address }}</td>
            </tr>
            <tr>
                <td class="label">Bác sĩ điều trị:</td>
                <td colspan="3">{{ $prescription->doctor?->name }}</td>
            </tr>
        </table>
    </div>

    @if($prescription->prescription_name)
        <p><strong>Tên đơn thuốc:</strong> {{ $prescription->prescription_name }}</p>
    @endif

    <div class="medications">
        <table>
            <thead>
                <tr>
                    <th class="stt">STT</th>
                    <th>Tên thuốc</th>
                    <th>Liều dùng</th>
                    <th class="qty">Số lượng</th>
                    <th>Cách dùng</th>
                    <th>Thời gian</th>
                </tr>
            </thead>
            <tbody>
                @forelse($prescription->items as $index => $item)
                    <tr>
                        <td class="stt">{{ $index + 1 }}</td>
                        <td><strong>{{ $item->medication_name }}</strong></td>
                        <td>{{ $item->dosage }}</td>
                        <td class="qty">{{ $item->quantity }} {{ $item->unit }}</td>
                        <td>{{ $item->instructions }}</td>
                        <td>{{ $item->duration }}</td>
                    </tr>
                    @if($item->notes)
                        <tr>
                            <td></td>
                            <td colspan="5" class="medication-note-cell">
                                <em>Ghi chú: {{ $item->notes }}</em>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="medications-empty">Không có thuốc trong đơn</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($prescription->notes)
        <div class="notes">
            <strong>Lưu ý của bác sĩ:</strong>
            {{ $prescription->notes }}
        </div>
    @endif

    <div class="footer">
        <div class="left">
            <p><strong>Lưu ý:</strong></p>
            <ul class="footer-notes-list">
                <li>Đọc kỹ hướng dẫn sử dụng trước khi dùng</li>
                <li>Uống đúng liều, đúng giờ theo chỉ định</li>
                <li>Tái khám theo lịch hẹn</li>
            </ul>
        </div>
        <div class="right">
            <p class="date">Ngày {{ now()->format('d') }} tháng {{ now()->format('m') }} năm {{ now()->format('Y') }}
            </p>
            <p><strong>Bác sĩ điều trị</strong></p>
            <p class="signature-line">{{ $prescription->doctor?->name }}</p>
        </div>
    </div>
</body>

@if(!($isPdf ?? false))
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
@endif

</html>
