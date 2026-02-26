<?php

namespace App\Services;

use App\Models\RecallRule;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Collection;

class RecallRuleEngineService
{
    /**
     * @return array{rule_id:int|null,offset_days:int,care_channel:string}
     */
    public function resolve(?int $serviceId, ?int $branchId): array
    {
        $candidates = $this->queryCandidates($serviceId, $branchId);
        $rule = $this->pickBestMatch($candidates, $serviceId, $branchId);

        return [
            'rule_id' => $rule?->id,
            'offset_days' => $rule ? max(0, (int) $rule->offset_days) : ClinicRuntimeSettings::recallDefaultOffsetDays(),
            'care_channel' => $rule?->care_channel ?: ClinicRuntimeSettings::defaultCareChannel(),
        ];
    }

    /**
     * @return Collection<int, RecallRule>
     */
    protected function queryCandidates(?int $serviceId, ?int $branchId): Collection
    {
        return RecallRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($serviceId): void {
                if ($serviceId !== null) {
                    $query->where('service_id', $serviceId)
                        ->orWhereNull('service_id');

                    return;
                }

                $query->whereNull('service_id');
            })
            ->where(function ($query) use ($branchId): void {
                if ($branchId !== null) {
                    $query->where('branch_id', $branchId)
                        ->orWhereNull('branch_id');

                    return;
                }

                $query->whereNull('branch_id');
            })
            ->orderBy('priority')
            ->orderByDesc('updated_at')
            ->get();
    }

    protected function pickBestMatch(Collection $candidates, ?int $serviceId, ?int $branchId): ?RecallRule
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sort(function (RecallRule $left, RecallRule $right) use ($serviceId, $branchId): int {
                $leftScore = $this->calculateRuleScore($left, $serviceId, $branchId);
                $rightScore = $this->calculateRuleScore($right, $serviceId, $branchId);

                if ($leftScore !== $rightScore) {
                    return $rightScore <=> $leftScore;
                }

                if ((int) $left->priority !== (int) $right->priority) {
                    return (int) $left->priority <=> (int) $right->priority;
                }

                return (int) $left->id <=> (int) $right->id;
            })
            ->first();
    }

    protected function calculateRuleScore(RecallRule $rule, ?int $serviceId, ?int $branchId): int
    {
        $score = 0;

        if ($serviceId !== null && (int) $rule->service_id === $serviceId) {
            $score += 20;
        } elseif ($rule->service_id === null) {
            $score += 5;
        }

        if ($branchId !== null && (int) $rule->branch_id === $branchId) {
            $score += 10;
        } elseif ($rule->branch_id === null) {
            $score += 3;
        }

        return $score;
    }
}
