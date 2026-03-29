<?php

namespace App\Services;

use App\Models\Disease;
use App\Models\ToothCondition;

class PatientExamReferenceReadModelService
{
    /**
     * @return array{
     *     conditions: \Illuminate\Support\Collection<int, ToothCondition>,
     *     conditionsJson: array<int, array{
     *         code:string,
     *         name:string,
     *         category:?string,
     *         color:?string,
     *         display_code:string
     *     }>,
     *     conditionOrder: array<int, string>
     * }
     */
    public function toothConditionsPayload(): array
    {
        $conditions = ToothCondition::query()
            ->ordered()
            ->get()
            ->values();

        if (! $conditions->contains(fn (ToothCondition $condition): bool => strtoupper((string) $condition->code) === 'KHAC')) {
            $conditions->push(new ToothCondition([
                'code' => 'KHAC',
                'name' => '(*) Khác',
                'category' => 'Khác',
                'color' => '#9ca3af',
            ]));
        }

        $conditions = $conditions->values();

        return [
            'conditions' => $conditions,
            'conditionsJson' => $conditions
                ->map(fn (ToothCondition $condition): array => [
                    'code' => (string) $condition->code,
                    'name' => (string) $condition->name,
                    'category' => $condition->category,
                    'color' => $condition->color,
                    'display_code' => $this->conditionDisplayCode($condition),
                ])
                ->values()
                ->all(),
            'conditionOrder' => $conditions
                ->pluck('code')
                ->map(fn ($code): string => (string) $code)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array{code:string, label:string, group:string}>
     */
    public function otherDiagnosisOptions(): array
    {
        return Disease::query()
            ->active()
            ->with(['diseaseGroup:id,name,sort_order'])
            ->get()
            ->sortBy(fn (Disease $disease): string => sprintf(
                '%06d-%s',
                (int) ($disease->diseaseGroup?->sort_order ?? 0),
                (string) $disease->code,
            ))
            ->values()
            ->map(fn (Disease $disease): array => [
                'code' => (string) $disease->code,
                'label' => $disease->full_name,
                'group' => (string) ($disease->diseaseGroup?->name ?? 'Khác'),
            ])
            ->values()
            ->all();
    }

    protected function conditionDisplayCode(ToothCondition $condition): string
    {
        $name = (string) ($condition->name ?? '');

        if (preg_match('/^\(([^)]+)\)/', $name, $matches) === 1) {
            return strtoupper(str_replace(' ', '', $matches[1]));
        }

        return strtoupper((string) $condition->code);
    }
}
