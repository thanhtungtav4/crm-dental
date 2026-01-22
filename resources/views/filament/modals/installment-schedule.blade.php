<div class="space-y-4">
    @if($plan->schedule)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-4 py-2 text-left font-semibold">Kỳ</th>
                        <th class="px-4 py-2 text-left font-semibold">Ngày đến hạn</th>
                        <th class="px-4 py-2 text-right font-semibold">Số tiền</th>
                        <th class="px-4 py-2 text-center font-semibold">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plan->schedule as $installment)
                        @php
                            $dueDate = \Carbon\Carbon::parse($installment['due_date']);
                            $isPast = $dueDate->isPast();
                            $isNear = $dueDate->diffInDays(now(), false) <= 7 && !$isPast;
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3">Kỳ {{ $installment['installment_number'] }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ $isPast && $installment['status'] !== 'paid' ? 'text-red-600 font-semibold' : '' }}">
                                    {{ $dueDate->format('d/m/Y') }}
                                </span>
                                @if($isNear && $installment['status'] !== 'paid')
                                    <span class="ml-2 text-xs text-yellow-600">⏰ Sắp đến hạn</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium">
                                {{ number_format($installment['amount'], 0, ',', '.') }}đ
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($installment['status'] === 'paid')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        ✓ Đã thanh toán
                                    </span>
                                @elseif($installment['status'] === 'overdue' || ($isPast && $installment['status'] !== 'paid'))
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        ⚠ Quá hạn
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        ○ Chờ thanh toán
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 bg-gray-50">
                        <td colspan="2" class="px-4 py-3 font-semibold">Tổng cộng</td>
                        <td class="px-4 py-3 text-right font-bold text-lg">
                            {{ number_format($plan->total_amount, 0, ',', '.') }}đ
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs text-gray-600">
                                {{ count(array_filter($plan->schedule, fn($s) => $s['status'] === 'paid')) }} / {{ count($plan->schedule) }} kỳ
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="grid grid-cols-3 gap-4 mt-4 p-4 bg-gray-50 rounded-lg">
            <div>
                <div class="text-xs text-gray-600">Đã thanh toán</div>
                <div class="text-lg font-bold text-green-600">
                    {{ number_format($plan->paid_amount, 0, ',', '.') }}đ
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-600">Còn lại</div>
                <div class="text-lg font-bold text-red-600">
                    {{ number_format($plan->remaining_amount, 0, ',', '.') }}đ
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-600">Tiến độ</div>
                <div class="text-lg font-bold text-blue-600">
                    {{ $plan->getCompletionPercentage() }}%
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-8 text-gray-500">
            <p>Chưa có lịch trả góp</p>
            <p class="text-sm mt-2">Lịch trả góp sẽ được tạo tự động sau khi lưu kế hoạch</p>
        </div>
    @endif
</div>
