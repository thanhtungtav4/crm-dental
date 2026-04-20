<?php

namespace App\Support;

class ToothChartViewConfig
{
    /**
     * @return list<array{mode:string, label:string}>
     */
    public function dentitionOptions(): array
    {
        return [
            ['mode' => 'auto', 'label' => 'Tự động'],
            ['mode' => 'adult', 'label' => 'Người lớn'],
            ['mode' => 'child', 'label' => 'Trẻ em'],
        ];
    }

    public function dentitionOptionActiveStyle(): string
    {
        return 'min-width: 78px; height: 30px; padding: 0 10px; border: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; background: var(--crm-primary, #2563eb); color: #fff; box-shadow: 0 1px 2px var(--crm-primary-shadow, rgba(37, 99, 235, .3));';
    }

    public function dentitionOptionIdleStyle(): string
    {
        return 'min-width: 78px; height: 30px; padding: 0 10px; border: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; background: transparent; color: var(--crm-text-body, #475569); box-shadow: none;';
    }

    /**
     * @param  array{upper:list<int>, lower:list<int>}  $adultTeeth
     * @param  array{upper:list<int>, lower:list<int>}  $childTeeth
     * @return list<array{
     *     mode:string,
     *     show_expression:string,
     *     teeth:list<int>,
     *     tooth_prefix:string,
     *     is_child:bool,
     *     show_status_list:bool,
     *     number_position:string
     * }>
     */
    public function toothRows(array $adultTeeth, array $childTeeth): array
    {
        return [
            [
                'mode' => 'adult',
                'show_expression' => 'showAdultTeeth()',
                'teeth' => $adultTeeth['upper'],
                'tooth_prefix' => 'Răng',
                'is_child' => false,
                'show_status_list' => true,
                'number_position' => 'top',
            ],
            [
                'mode' => 'child',
                'show_expression' => 'showChildTeeth()',
                'teeth' => $childTeeth['upper'],
                'tooth_prefix' => 'Răng sữa',
                'is_child' => true,
                'show_status_list' => false,
                'number_position' => 'bottom',
            ],
            [
                'mode' => 'child',
                'show_expression' => 'showChildTeeth()',
                'teeth' => $childTeeth['lower'],
                'tooth_prefix' => 'Răng sữa',
                'is_child' => true,
                'show_status_list' => false,
                'number_position' => 'top',
            ],
            [
                'mode' => 'adult',
                'show_expression' => 'showAdultTeeth()',
                'teeth' => $adultTeeth['lower'],
                'tooth_prefix' => 'Răng',
                'is_child' => false,
                'show_status_list' => true,
                'number_position' => 'bottom',
            ],
        ];
    }

    /**
     * @return list<array{label:string, swatch_classes:string}>
     */
    public function treatmentLegend(): array
    {
        return [
            [
                'label' => 'Tình trạng hiện tại',
                'swatch_classes' => 'inline-block h-3 w-3 rounded-sm bg-gray-500',
            ],
            [
                'label' => 'Đang được điều trị',
                'swatch_classes' => 'inline-block h-3 w-3 rounded-sm bg-red-500',
            ],
            [
                'label' => 'Hoàn thành điều trị',
                'swatch_classes' => 'inline-block h-3 w-3 rounded-sm bg-green-500',
            ],
        ];
    }

    public function selectionHint(): string
    {
        return '* Dùng nút "Chọn nhiều" (hỗ trợ mobile) hoặc giữ phím "Ctrl/Command" để chọn nhiều răng trước khi chẩn đoán.';
    }
}
