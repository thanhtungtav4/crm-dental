<?php

use App\Models\Branch;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use App\Services\CustomerCareSlaReadModelService;

function makeCareTicket(array $overrides = []): Note
{
    $branch = $overrides['branch_id'] ?? Branch::factory()->create()->id;
    $patient = Patient::factory()->create(['first_branch_id' => $branch]);

    return Note::factory()->create(array_merge([
        'patient_id' => $patient->id,
        'branch_id' => $branch,
        'care_type' => 'recall_recare',
        'care_channel' => 'zalo',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'user_id' => null,
        'care_at' => null,
    ], $overrides));
}

describe('CustomerCareSlaReadModelService::summary()', function (): void {

    it('returns correct total_open count', function (): void {
        $branch = Branch::factory()->create();
        makeCareTicket(['branch_id' => $branch->id]);
        makeCareTicket(['branch_id' => $branch->id]);

        $query = Note::query()
            ->whereIn('care_status', [Note::CARE_STATUS_NOT_STARTED, Note::CARE_STATUS_IN_PROGRESS, Note::CARE_STATUS_NEED_FOLLOWUP])
            ->where('branch_id', $branch->id);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        expect($result['total_open'])->toBe(2)
            ->and($result)->toHaveKeys([
                'total_open', 'overdue', 'due_today', 'unassigned',
                'owned_by_me', 'priority_no_show', 'priority_recall', 'priority_follow_up',
                'by_channel', 'by_branch', 'by_staff',
            ]);
    });

    it('counts overdue tickets correctly', function (): void {
        $branch = Branch::factory()->create();
        makeCareTicket(['branch_id' => $branch->id, 'care_at' => now()->subHour(), 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'care_at' => now()->addDay(), 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'care_at' => null, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()->where('care_status', Note::CARE_STATUS_NOT_STARTED)->where('branch_id', $branch->id);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        expect($result['overdue'])->toBe(1);
    });

    it('counts due_today tickets correctly', function (): void {
        $branch = Branch::factory()->create();
        makeCareTicket(['branch_id' => $branch->id, 'care_at' => now()->startOfDay()->addHours(9), 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'care_at' => now()->subDay(), 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()->where('care_status', Note::CARE_STATUS_NOT_STARTED)->where('branch_id', $branch->id);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        expect($result['due_today'])->toBe(1);
    });

    it('counts unassigned tickets (null user_id)', function (): void {
        $branch = Branch::factory()->create();
        $staff = User::factory()->create(['branch_id' => $branch->id]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => null, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => $staff->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()->where('care_status', Note::CARE_STATUS_NOT_STARTED)->where('branch_id', $branch->id);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        expect($result['unassigned'])->toBe(1);
    });

    it('counts owned_by_me correctly when actor is set', function (): void {
        $branch = Branch::factory()->create();
        $me = User::factory()->create(['branch_id' => $branch->id]);
        $other = User::factory()->create(['branch_id' => $branch->id]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => $me->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => $me->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => $other->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()->where('care_status', Note::CARE_STATUS_NOT_STARTED)->where('branch_id', $branch->id);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, $me, []);

        expect($result['owned_by_me'])->toBe(2);
    });

    it('returns owned_by_me=0 when actor is null', function (): void {
        $branch = Branch::factory()->create();
        $staff = User::factory()->create(['branch_id' => $branch->id]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => $staff->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()->where('care_status', Note::CARE_STATUS_NOT_STARTED)->where('branch_id', $branch->id);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        expect($result['owned_by_me'])->toBe(0);
    });

    it('counts priority care types correctly', function (): void {
        $branch = Branch::factory()->create();
        makeCareTicket(['branch_id' => $branch->id, 'care_type' => 'no_show_recovery', 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'care_type' => 'recall_recare', 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'care_type' => 'treatment_plan_follow_up', 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()->where('care_status', Note::CARE_STATUS_NOT_STARTED)->where('branch_id', $branch->id);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        expect($result['priority_no_show'])->toBe(1)
            ->and($result['priority_recall'])->toBe(1)
            ->and($result['priority_follow_up'])->toBe(1);
    });

    it('groups by_channel with resolved labels', function (): void {
        $branch = Branch::factory()->create();
        makeCareTicket(['branch_id' => $branch->id, 'care_channel' => 'zalo', 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'care_channel' => 'zalo', 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'care_channel' => 'phone', 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()->where('care_status', Note::CARE_STATUS_NOT_STARTED)->where('branch_id', $branch->id);

        $channelOptions = ['zalo' => 'Zalo', 'phone' => 'Điện thoại'];
        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, $channelOptions);

        $channelTotals = collect($result['by_channel'])->pluck('total', 'label');
        expect($channelTotals->get('Zalo'))->toBe(2)
            ->and($channelTotals->get('Điện thoại'))->toBe(1);
    });

    it('groups by_branch with resolved branch names', function (): void {
        $branch1 = Branch::factory()->create(['name' => 'Chi nhánh A']);
        $branch2 = Branch::factory()->create(['name' => 'Chi nhánh B']);
        makeCareTicket(['branch_id' => $branch1->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch2->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch2->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()
            ->where('care_status', Note::CARE_STATUS_NOT_STARTED)
            ->whereIn('branch_id', [$branch1->id, $branch2->id]);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        $branchTotals = collect($result['by_branch'])->pluck('total', 'label');
        expect($branchTotals->get('Chi nhánh B'))->toBe(2)
            ->and($branchTotals->get('Chi nhánh A'))->toBe(1);
    });

    it('groups by_staff with resolved staff names', function (): void {
        $branch = Branch::factory()->create();
        $staffA = User::factory()->create(['name' => 'Nguyễn A', 'branch_id' => $branch->id]);
        $staffB = User::factory()->create(['name' => 'Trần B', 'branch_id' => $branch->id]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => $staffA->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => $staffA->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);
        makeCareTicket(['branch_id' => $branch->id, 'user_id' => $staffB->id, 'care_status' => Note::CARE_STATUS_NOT_STARTED]);

        $query = Note::query()->where('care_status', Note::CARE_STATUS_NOT_STARTED)->where('branch_id', $branch->id);

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        $staffTotals = collect($result['by_staff'])->pluck('total', 'label');
        expect($staffTotals->get('Nguyễn A'))->toBe(2)
            ->and($staffTotals->get('Trần B'))->toBe(1);
    });

    it('returns zero counts when query returns no rows', function (): void {
        $query = Note::query()->whereRaw('1 = 0');

        $result = app(CustomerCareSlaReadModelService::class)->summary($query, null, []);

        expect($result['total_open'])->toBe(0)
            ->and($result['overdue'])->toBe(0)
            ->and($result['due_today'])->toBe(0)
            ->and($result['unassigned'])->toBe(0)
            ->and($result['by_channel'])->toBe([])
            ->and($result['by_branch'])->toBe([])
            ->and($result['by_staff'])->toBe([]);
    });
});
