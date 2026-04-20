<?php

namespace App\Support;

use App\Models\PatientToothCondition;

class ToothSelectionViewConfig
{
    /**
     * @return list<array{key:string, label:string}>
     */
    public function pickerTabs(): array
    {
        return [
            ['key' => 'adult', 'label' => 'Người lớn'],
            ['key' => 'child', 'label' => 'Trẻ em'],
        ];
    }

    /**
     * @return array{
     *     adult: list<array{label:string, teeth:list<int>, grid_class:string, grid_style:string, button_classes:string}>,
     *     child: list<array{label:string, teeth:list<int>, grid_class:string, grid_style:string, button_classes:string}>
     * }
     */
    public function pickerToothGroups(): array
    {
        return [
            'adult' => [
                $this->pickerToothGroup(
                    label: 'Hàm trên',
                    teeth: array_map('intval', PatientToothCondition::getAdultTeethUpper()),
                    gridClass: 'crm-tooth-picker-grid-16',
                    gridStyle: 'grid-template-columns: repeat(16, minmax(0, 1fr));',
                    buttonClasses: 'flex h-12 items-center justify-center rounded border bg-white transition-all hover:shadow-md dark:bg-gray-900 dark:hover:bg-gray-800',
                ),
                $this->pickerToothGroup(
                    label: 'Hàm dưới',
                    teeth: array_map('intval', PatientToothCondition::getAdultTeethLower()),
                    gridClass: 'crm-tooth-picker-grid-16',
                    gridStyle: 'grid-template-columns: repeat(16, minmax(0, 1fr));',
                    buttonClasses: 'flex h-12 items-center justify-center rounded border bg-white transition-all hover:shadow-md dark:bg-gray-900 dark:hover:bg-gray-800',
                ),
            ],
            'child' => [
                $this->pickerToothGroup(
                    label: 'Răng sữa hàm trên',
                    teeth: array_map('intval', PatientToothCondition::getChildTeethUpper()),
                    gridClass: 'crm-tooth-picker-grid-10',
                    gridStyle: 'grid-template-columns: repeat(10, minmax(0, 1fr));',
                    buttonClasses: 'flex h-11 items-center justify-center rounded border bg-white transition-all hover:shadow-md dark:bg-gray-900 dark:hover:bg-gray-800',
                ),
                $this->pickerToothGroup(
                    label: 'Răng sữa hàm dưới',
                    teeth: array_map('intval', PatientToothCondition::getChildTeethLower()),
                    gridClass: 'crm-tooth-picker-grid-10',
                    gridStyle: 'grid-template-columns: repeat(10, minmax(0, 1fr));',
                    buttonClasses: 'flex h-11 items-center justify-center rounded border bg-white transition-all hover:shadow-md dark:bg-gray-900 dark:hover:bg-gray-800',
                ),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function childTeethFlat(): array
    {
        return array_merge(
            PatientToothCondition::getChildTeethUpper(),
            PatientToothCondition::getChildTeethLower(),
        );
    }

    public function selectedPickerButtonClasses(): string
    {
        return 'border-primary-500 bg-primary-50 ring-2 ring-primary-500 ring-offset-1 ring-offset-white dark:bg-primary-500/15 dark:ring-offset-gray-900';
    }

    public function defaultPickerButtonClasses(): string
    {
        return 'border-gray-200 dark:border-gray-700';
    }

    public function selectedPickerLabelClasses(): string
    {
        return 'text-primary-700 dark:text-primary-100';
    }

    public function defaultPickerLabelClasses(): string
    {
        return 'text-gray-600 dark:text-gray-300';
    }

    /**
     * @return list<array{teeth:list<string>, button_classes:string, row_classes:string, divider_after:bool}>
     */
    public function selectorRows(): array
    {
        return [
            [
                'teeth' => PatientToothCondition::getAdultTeethUpper(),
                'button_classes' => 'h-10 w-8 rounded border-2 text-xs font-medium transition-all',
                'row_classes' => 'mb-2 flex justify-center gap-1',
                'divider_after' => false,
            ],
            [
                'teeth' => PatientToothCondition::getChildTeethUpper(),
                'button_classes' => 'h-8 w-6 rounded border-2 text-[10px] font-medium opacity-80 transition-all',
                'row_classes' => 'mb-4 flex justify-center gap-1',
                'divider_after' => true,
            ],
            [
                'teeth' => PatientToothCondition::getChildTeethLower(),
                'button_classes' => 'h-8 w-6 rounded border-2 text-[10px] font-medium opacity-80 transition-all',
                'row_classes' => 'mb-2 flex justify-center gap-1',
                'divider_after' => false,
            ],
            [
                'teeth' => PatientToothCondition::getAdultTeethLower(),
                'button_classes' => 'h-10 w-8 rounded border-2 text-xs font-medium transition-all',
                'row_classes' => 'flex justify-center gap-1',
                'divider_after' => false,
            ],
        ];
    }

    /**
     * @return list<array{label:string, swatch_classes:string}>
     */
    public function selectorLegendItems(): array
    {
        return [
            [
                'label' => 'Đang chọn',
                'swatch_classes' => 'h-4 w-4 rounded border-2 border-primary-500 bg-primary-50 dark:bg-primary-500/15',
            ],
            [
                'label' => 'Bình thường',
                'swatch_classes' => 'h-4 w-4 rounded border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-900',
            ],
        ];
    }

    public function selectedSelectorButtonClasses(): string
    {
        return 'bg-primary-50 border-primary-500 text-primary-700 dark:bg-primary-500/15 dark:text-primary-100';
    }

    public function defaultSelectorButtonClasses(): string
    {
        return 'bg-white border-gray-300 text-gray-500 hover:border-gray-400 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300 dark:hover:border-gray-500';
    }

    public function emptySelectionLabel(): string
    {
        return 'Chưa chọn';
    }

    /**
     * @param  list<int>  $teeth
     * @return array{label:string, teeth:list<int>, grid_class:string, grid_style:string, button_classes:string}
     */
    protected function pickerToothGroup(
        string $label,
        array $teeth,
        string $gridClass,
        string $gridStyle,
        string $buttonClasses,
    ): array {
        return [
            'label' => $label,
            'teeth' => $teeth,
            'grid_class' => $gridClass,
            'grid_style' => $gridStyle,
            'button_classes' => $buttonClasses,
        ];
    }
}
