<?php

namespace App\Support;

use App\Models\PatientToothCondition;
use Illuminate\Database\Eloquent\Collection;

class ToothChartModalViewState
{
    /**
     * @param  Collection<int, PatientToothCondition>  $toothConditions
     * @return array<string, mixed>
     */
    public function build(Collection $toothConditions): array
    {
        $conditions = $toothConditions->loadMissing('condition')->values();

        return [
            'palette' => $this->palette($conditions),
            'legend' => $this->legend(),
            'rows' => $this->rows($conditions),
            'summary' => $this->summary($conditions),
            'summary_heading' => 'Tình trạng răng hiện tại',
            'footer_note' => 'Click vào răng để xem chi tiết hoặc thêm tình trạng mới từ bảng bên trên.',
            'has_conditions' => $conditions->isNotEmpty(),
        ];
    }

    /**
     * @return list<array{label:string, swatch_classes:string}>
     */
    protected function legend(): array
    {
        return [
            [
                'label' => 'Tình trạng hiện tại',
                'swatch_classes' => 'h-4 w-4 rounded bg-gray-400',
            ],
            [
                'label' => 'Đang điều trị',
                'swatch_classes' => 'h-4 w-4 rounded bg-red-500',
            ],
            [
                'label' => 'Hoàn thành điều trị',
                'swatch_classes' => 'h-4 w-4 rounded bg-green-500',
            ],
        ];
    }

    /**
     * @param  Collection<int, PatientToothCondition>  $toothConditions
     * @return list<array{id:int, color:string}>
     */
    protected function palette(Collection $toothConditions): array
    {
        return $toothConditions
            ->map(fn (PatientToothCondition $item) => $item->condition)
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($condition): array => [
                'id' => (int) $condition->id,
                'color' => (string) ($condition->color ?? '#666'),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, PatientToothCondition>  $toothConditions
     * @return list<array<string, mixed>>
     */
    protected function rows(Collection $toothConditions): array
    {
        return [
            $this->buildRow(
                label: 'Hàm trên người lớn',
                toothNumbers: PatientToothCondition::getAdultTeethUpper(),
                toothConditions: $toothConditions,
                toothLabelPrefix: 'Răng',
                buttonSizeClasses: 'w-10 h-12 text-xs',
                codeSizeClasses: 'text-[8px]',
                defaultStatusClasses: 'bg-white border-gray-300 text-gray-500 hover:border-blue-400',
            ),
            $this->buildRow(
                label: 'Răng sữa trên',
                toothNumbers: PatientToothCondition::getChildTeethUpper(),
                toothConditions: $toothConditions,
                toothLabelPrefix: 'Răng sữa',
                buttonSizeClasses: 'w-8 h-10 text-[10px]',
                codeSizeClasses: 'text-[6px]',
                defaultStatusClasses: 'bg-white border-gray-300 text-gray-400 hover:border-blue-400',
                hasSpacer: true,
            ),
            $this->buildRow(
                label: 'Răng sữa dưới',
                toothNumbers: PatientToothCondition::getChildTeethLower(),
                toothConditions: $toothConditions,
                toothLabelPrefix: 'Răng sữa',
                buttonSizeClasses: 'w-8 h-10 text-[10px]',
                codeSizeClasses: 'text-[6px]',
                defaultStatusClasses: 'bg-white border-gray-300 text-gray-400 hover:border-blue-400',
                hasSpacer: true,
            ),
            $this->buildRow(
                label: 'Hàm dưới người lớn',
                toothNumbers: PatientToothCondition::getAdultTeethLower(),
                toothConditions: $toothConditions,
                toothLabelPrefix: 'Răng',
                buttonSizeClasses: 'w-10 h-12 text-xs',
                codeSizeClasses: 'text-[8px]',
                defaultStatusClasses: 'bg-white border-gray-300 text-gray-500 hover:border-blue-400',
            ),
        ];
    }

    /**
     * @param  array<int, string>  $toothNumbers
     * @param  Collection<int, PatientToothCondition>  $toothConditions
     * @return array<string, mixed>
     */
    protected function buildRow(
        string $label,
        array $toothNumbers,
        Collection $toothConditions,
        string $toothLabelPrefix,
        string $buttonSizeClasses,
        string $codeSizeClasses,
        string $defaultStatusClasses,
        bool $hasSpacer = false,
    ): array {
        return [
            'label' => $label,
            'has_spacer' => $hasSpacer,
            'teeth' => collect($toothNumbers)
                ->map(fn (string $toothNumber): array => $this->buildTooth(
                    toothNumber: $toothNumber,
                    toothConditions: $toothConditions->where('tooth_number', $toothNumber)->values(),
                    toothLabelPrefix: $toothLabelPrefix,
                    buttonSizeClasses: $buttonSizeClasses,
                    codeSizeClasses: $codeSizeClasses,
                    defaultStatusClasses: $defaultStatusClasses,
                ))
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, PatientToothCondition>  $toothConditions
     * @return array<string, mixed>
     */
    protected function buildTooth(
        string $toothNumber,
        Collection $toothConditions,
        string $toothLabelPrefix,
        string $buttonSizeClasses,
        string $codeSizeClasses,
        string $defaultStatusClasses,
    ): array {
        $firstCondition = $toothConditions->first();
        $conditionNames = $toothConditions
            ->map(fn (PatientToothCondition $condition): ?string => $condition->condition?->name)
            ->filter()
            ->join(', ');
        $tooltip = trim($toothLabelPrefix.' '.$toothNumber.($conditionNames !== '' ? ' - '.$conditionNames : ''));
        $displayCode = null;
        $codeClass = 'crm-tooth-condition-default';

        if ($firstCondition !== null) {
            $displayCode = $toothConditions->count() > 1
                ? '*'.$toothConditions->count()
                : ($firstCondition->condition?->code ?? '');
            $codeClass = $firstCondition->condition?->id
                ? 'crm-tooth-condition-'.$firstCondition->condition->id
                : 'crm-tooth-condition-default';
        }

        return [
            'number' => $toothNumber,
            'has_conditions' => $toothConditions->isNotEmpty(),
            'tooltip' => $tooltip,
            'button_classes' => trim('tooth-btn '.$buttonSizeClasses.' rounded border-2 font-medium transition-all hover:scale-105 relative '.$this->statusClasses($toothConditions, $defaultStatusClasses)),
            'display_code' => $displayCode,
            'code_classes' => trim('absolute -bottom-1 left-1/2 -translate-x-1/2 font-bold '.$codeSizeClasses.' '.$codeClass),
        ];
    }

    /**
     * @param  Collection<int, PatientToothCondition>  $toothConditions
     */
    protected function statusClasses(Collection $toothConditions, string $defaultStatusClasses): string
    {
        if ($toothConditions->isEmpty()) {
            return $defaultStatusClasses;
        }

        if ($toothConditions->contains('treatment_status', PatientToothCondition::STATUS_IN_TREATMENT)) {
            return 'bg-red-100 border-red-500 text-red-700';
        }

        if ($toothConditions->contains('treatment_status', PatientToothCondition::STATUS_COMPLETED)) {
            return 'bg-green-100 border-green-500 text-green-700';
        }

        if ($toothConditions->contains('treatment_status', PatientToothCondition::STATUS_CURRENT)) {
            return 'bg-gray-100 border-gray-400 text-gray-700';
        }

        return $defaultStatusClasses;
    }

    /**
     * @param  Collection<int, PatientToothCondition>  $toothConditions
     * @return list<array{name:string, tooth_numbers:string, condition_class:string}>
     */
    protected function summary(Collection $toothConditions): array
    {
        return $toothConditions
            ->groupBy(fn (PatientToothCondition $condition): string => (string) ($condition->condition?->name ?: 'Không xác định'))
            ->map(function (Collection $conditions, string $conditionName): array {
                $firstCondition = $conditions->first();

                return [
                    'name' => $conditionName,
                    'tooth_numbers' => $conditions
                        ->pluck('tooth_number')
                        ->map(fn ($toothNumber): string => (string) $toothNumber)
                        ->join(', '),
                    'condition_class' => $firstCondition?->condition?->id
                        ? 'crm-tooth-condition-'.$firstCondition->condition->id
                        : 'crm-tooth-condition-default',
                ];
            })
            ->values()
            ->all();
    }
}
