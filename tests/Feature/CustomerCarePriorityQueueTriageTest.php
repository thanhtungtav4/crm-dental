<?php

use App\Filament\Pages\CustomerCare;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

it('shows triage buckets and sla cues for priority queue tickets', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-28 09:00:00'));

    try {
        [$manager, $records] = makePriorityQueueScenario();

        $this->actingAs($manager);

        Livewire::test(CustomerCare::class)
            ->call('setActiveTab', 'priority_queue')
            ->assertCanSeeTableRecords(array_values($records))
            ->assertSee('Ưu tiên xử lý')
            ->assertSee('Quá hạn')
            ->assertSee('Đến hạn hôm nay')
            ->assertSee('Sắp tới')
            ->assertSee('Chưa đặt lịch')
            ->assertSee('Quá hạn 2 giờ')
            ->assertSee('Đến hạn trong 2 giờ')
            ->assertSee('Còn 1 ngày');
    } finally {
        Carbon::setTestNow();
    }
});

it('filters priority queue tickets by triage bucket', function (string $bucket): void {
    Carbon::setTestNow(Carbon::parse('2026-03-28 09:00:00'));

    try {
        [$manager, $records] = makePriorityQueueScenario();

        $expected = $records[$bucket];
        $others = collect($records)
            ->reject(fn (Note $record, string $key): bool => $key === $bucket)
            ->values()
            ->all();

        $this->actingAs($manager);

        Livewire::test(CustomerCare::class)
            ->call('setActiveTab', 'priority_queue')
            ->set('tableFilters.triage_bucket.value', $bucket)
            ->assertCanSeeTableRecords([$expected])
            ->assertCanNotSeeTableRecords($others);
    } finally {
        Carbon::setTestNow();
    }
})->with([
    'overdue bucket' => 'overdue',
    'due today bucket' => 'due_today',
    'upcoming bucket' => 'upcoming',
    'unscheduled bucket' => 'unscheduled',
]);

/**
 * @return array{0: User, 1: array{overdue: Note, due_today: Note, upcoming: Note, unscheduled: Note}}
 */
function makePriorityQueueScenario(): array
{
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $records = [
        'overdue' => makePriorityQueueNote($branch, $manager, 'no_show_recovery', now()->subHours(2), 'Priority Overdue'),
        'due_today' => makePriorityQueueNote($branch, $manager, 'recall_recare', now()->addHours(2), 'Priority Today'),
        'upcoming' => makePriorityQueueNote($branch, $manager, 'treatment_plan_follow_up', now()->addDay(), 'Priority Upcoming'),
        'unscheduled' => makePriorityQueueNote($branch, $manager, 'no_show_recovery', null, 'Priority Unscheduled'),
    ];

    return [$manager, $records];
}

function makePriorityQueueNote(Branch $branch, User $manager, string $careType, ?Carbon $careAt, string $name): Note
{
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'full_name' => $name,
        'phone' => fake()->numerify('09########'),
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $name,
        'phone' => $customer->phone,
    ]);

    return Note::query()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'user_id' => $manager->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => $careType,
        'care_channel' => 'phone',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_at' => $careAt,
        'content' => 'Follow up for '.$name,
    ]);
}
