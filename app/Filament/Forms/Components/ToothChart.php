<?php

namespace App\Filament\Forms\Components;

use App\Models\ToothCondition;
use App\Support\ToothChartViewConfig;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class ToothChart extends Field
{
    protected const ADULT_TEETH = [
        'upper' => [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28],
        'lower' => [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38],
    ];

    protected const CHILD_TEETH = [
        'upper' => [55, 54, 53, 52, 51, 61, 62, 63, 64, 65],
        'lower' => [85, 84, 83, 82, 81, 71, 72, 73, 74, 75],
    ];

    protected string $view = 'filament.forms.components.tooth-chart';

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $conditions = $this->conditions();
        $toothDiagnosisStatePath = $this->getStatePath();
        $viewConfig = app(ToothChartViewConfig::class);

        return [
            'conditions' => $conditions,
            'conditionsJson' => $this->conditionJson($conditions),
            'conditionOrder' => $this->conditionOrder($conditions),
            'dentitionOptions' => $viewConfig->dentitionOptions(),
            'dentitionOptionActiveStyle' => $viewConfig->dentitionOptionActiveStyle(),
            'dentitionOptionIdleStyle' => $viewConfig->dentitionOptionIdleStyle(),
            'toothRows' => $viewConfig->toothRows(self::ADULT_TEETH, self::CHILD_TEETH),
            'treatmentLegend' => $viewConfig->treatmentLegend(),
            'selectionHint' => $viewConfig->selectionHint(),
            'adultUpper' => self::ADULT_TEETH['upper'],
            'adultLower' => self::ADULT_TEETH['lower'],
            'childUpper' => self::CHILD_TEETH['upper'],
            'childLower' => self::CHILD_TEETH['lower'],
            'adultTeeth' => $this->flatToothSet(self::ADULT_TEETH),
            'childTeeth' => $this->flatToothSet(self::CHILD_TEETH),
            'toothDiagnosisStatePath' => $toothDiagnosisStatePath,
            'dentitionModeStatePath' => str_replace('tooth_diagnosis_data', 'tooth_chart_dentition_mode', $toothDiagnosisStatePath),
            'defaultDentitionModeStatePath' => str_replace('tooth_diagnosis_data', 'tooth_chart_default_dentition_mode', $toothDiagnosisStatePath),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
    }

    /**
     * @return Collection<int, ToothCondition>
     */
    protected function conditions(): Collection
    {
        $conditions = ToothCondition::query()->ordered()->get()->values();

        if (! $conditions->contains(fn (ToothCondition $condition): bool => strtoupper((string) $condition->code) === 'KHAC')) {
            $conditions->push(new ToothCondition([
                'code' => 'KHAC',
                'name' => '(*) Khác',
                'category' => 'Khác',
                'color' => '#9ca3af',
            ]));
        }

        return $conditions->values();
    }

    /**
     * @param  Collection<int, ToothCondition>  $conditions
     * @return array<int, array{code:mixed,name:mixed,category:mixed,color:mixed,display_code:string}>
     */
    protected function conditionJson(Collection $conditions): array
    {
        return $conditions
            ->map(function (ToothCondition $condition): array {
                $displayCode = strtoupper((string) $condition->code);

                if (preg_match('/^\(([^)]+)\)/', (string) $condition->name, $matches) === 1) {
                    $displayCode = strtoupper(str_replace(' ', '', $matches[1]));
                }

                return [
                    'code' => $condition->code,
                    'name' => $condition->name,
                    'category' => $condition->category,
                    'color' => $condition->color,
                    'display_code' => $displayCode,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ToothCondition>  $conditions
     * @return array<int, string>
     */
    protected function conditionOrder(Collection $conditions): array
    {
        return $conditions
            ->pluck('code')
            ->map(fn ($code): string => (string) $code)
            ->values()
            ->all();
    }

    /**
     * @param  array{upper:array<int, int>,lower:array<int, int>}  $teeth
     * @return array<int, string>
     */
    protected function flatToothSet(array $teeth): array
    {
        return array_map(
            'strval',
            array_merge($teeth['upper'], $teeth['lower']),
        );
    }
}
