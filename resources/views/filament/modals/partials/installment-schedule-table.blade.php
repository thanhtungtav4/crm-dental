@props(['viewState'])

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
            @foreach($viewState['rows'] as $installment)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $installment['installment_label'] }}</td>
                    <td class="px-4 py-3">
                        <span class="{{ $installment['due_date_classes'] }}">
                            {{ $installment['due_date_label'] }}
                        </span>
                        @if($installment['show_near_due_notice'])
                            <span class="ml-2 text-xs text-yellow-600">{{ $installment['near_due_label'] }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-medium">
                        {{ $installment['amount_label'] }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="{{ $installment['status_classes'] }}">
                            {{ $installment['status_label'] }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-300 bg-gray-50">
                <td colspan="2" class="px-4 py-3 font-semibold">Tổng cộng</td>
                <td class="px-4 py-3 text-right text-lg font-bold">
                    {{ $viewState['total_amount_label'] }}
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-xs text-gray-600">{{ $viewState['paid_progress_label'] }}</span>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
