<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class CustomerCareSlaReadModelService
{
    /**
     * @param  array<string, string>  $careChannelOptions
     * @return array{
     *   total_open:int,
     *   overdue:int,
     *   due_today:int,
     *   unassigned:int,
     *   owned_by_me:int,
     *   priority_no_show:int,
     *   priority_recall:int,
     *   priority_follow_up:int,
     *   by_channel:array<int, array{label:string,total:int}>,
     *   by_branch:array<int, array{label:string,total:int}>,
     *   by_staff:array<int, array{label:string,total:int}>
     * }
     */
    public function summary(Builder $baseQuery, ?User $authUser, array $careChannelOptions): array
    {
        $now = now();
        $today = $now->toDateString();

        $totalOpen = (clone $baseQuery)->count();
        $overdue = (clone $baseQuery)
            ->whereNotNull('care_at')
            ->where('care_at', '<', $now)
            ->count();
        $dueToday = (clone $baseQuery)
            ->whereDate('care_at', $today)
            ->count();
        $unassigned = (clone $baseQuery)
            ->whereNull('user_id')
            ->count();
        $ownedByMe = $this->ownedByCurrentUserCount($baseQuery, $authUser);

        $priorityNoShow = (clone $baseQuery)->where('care_type', 'no_show_recovery')->count();
        $priorityRecall = (clone $baseQuery)->where('care_type', 'recall_recare')->count();
        $priorityFollowUp = (clone $baseQuery)->where('care_type', 'treatment_plan_follow_up')->count();

        $byChannelRows = (clone $baseQuery)
            ->selectRaw('COALESCE(care_channel, "other") as metric_key, COUNT(*) as total')
            ->groupBy('metric_key')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $byBranchRows = (clone $baseQuery)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id as metric_key, COUNT(*) as total')
            ->groupBy('metric_key')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $byStaffRows = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->selectRaw('user_id as metric_key, COUNT(*) as total')
            ->groupBy('metric_key')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $branchNames = Branch::query()
            ->whereIn('id', $byBranchRows->pluck('metric_key')->filter()->map(static fn ($id): int => (int) $id)->all())
            ->pluck('name', 'id');

        $staffNames = User::query()
            ->whereIn('id', $byStaffRows->pluck('metric_key')->filter()->map(static fn ($id): int => (int) $id)->all())
            ->pluck('name', 'id');

        return [
            'total_open' => (int) $totalOpen,
            'overdue' => (int) $overdue,
            'due_today' => (int) $dueToday,
            'unassigned' => (int) $unassigned,
            'owned_by_me' => (int) $ownedByMe,
            'priority_no_show' => (int) $priorityNoShow,
            'priority_recall' => (int) $priorityRecall,
            'priority_follow_up' => (int) $priorityFollowUp,
            'by_channel' => $byChannelRows
                ->map(fn ($row): array => [
                    'label' => $this->formatCareChannel((string) $row->metric_key, $careChannelOptions),
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all(),
            'by_branch' => $byBranchRows
                ->map(fn ($row): array => [
                    'label' => (string) ($branchNames[(int) $row->metric_key] ?? 'Không xác định'),
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all(),
            'by_staff' => $byStaffRows
                ->map(fn ($row): array => [
                    'label' => (string) ($staffNames[(int) $row->metric_key] ?? 'Chưa phân công'),
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function ownedByCurrentUserCount(Builder $baseQuery, ?User $authUser): int
    {
        if (! $authUser instanceof User) {
            return 0;
        }

        return (clone $baseQuery)
            ->where('user_id', $authUser->id)
            ->count();
    }

    /**
     * @param  array<string, string>  $careChannelOptions
     */
    protected function formatCareChannel(?string $state, array $careChannelOptions): string
    {
        return Arr::get($careChannelOptions, $state, 'Khác');
    }
}
