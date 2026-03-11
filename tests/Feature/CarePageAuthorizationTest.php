<?php

use App\Filament\Pages\CustomerCare;
use App\Filament\Pages\Reports\CustomsCareStatistical;
use App\Models\Branch;
use App\Models\ReportCareQueueDailyAggregate;
use App\Models\User;
use Livewire\Livewire;

it('blocks doctors from accessing the customer care page', function (): void {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor)
        ->get(CustomerCare::getUrl())
        ->assertForbidden();
});

it('allows managers to access the customer care page', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(CustomerCare::getUrl())
        ->assertOk();
});

it('blocks doctors from accessing the care statistical report', function (): void {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor)
        ->get(CustomsCareStatistical::getUrl())
        ->assertForbidden();
});

it('blocks cskh from accessing the care statistical report', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $this->actingAs($cskh)
        ->get(CustomsCareStatistical::getUrl())
        ->assertForbidden();
});

it('scopes care statistical aggregates to accessible branches when no branch filter is selected', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'care_type' => 'no_show_recovery',
        'care_type_label' => 'No-show',
        'care_status' => 'not_started',
        'care_status_label' => 'Chua cham soc',
        'total_count' => 2,
        'latest_care_at' => now(),
        'generated_at' => now(),
    ]);

    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'care_type' => 'no_show_recovery',
        'care_type_label' => 'No-show',
        'care_status' => 'not_started',
        'care_status_label' => 'Chua cham soc',
        'total_count' => 5,
        'latest_care_at' => now(),
        'generated_at' => now(),
    ]);

    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'care_type' => 'no_show_recovery',
        'care_type_label' => 'No-show',
        'care_status' => 'not_started',
        'care_status_label' => 'Chua cham soc',
        'total_count' => 7,
        'latest_care_at' => now(),
        'generated_at' => now(),
    ]);

    $this->actingAs($manager);

    $stats = Livewire::test(CustomsCareStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance()
        ->getStats();

    expect($stats[0]['value'])->toBe(number_format(2))
        ->and($stats[2]['value'])->toBe(number_format(2));
});

it('returns empty care statistical aggregates when a non-admin forges an inaccessible branch filter', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'care_type' => 'recall_recare',
        'care_type_label' => 'Recall',
        'care_status' => 'not_started',
        'care_status_label' => 'Chua cham soc',
        'total_count' => 3,
        'latest_care_at' => now(),
        'generated_at' => now(),
    ]);

    $this->actingAs($manager);

    $component = Livewire::test(CustomsCareStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->set('tableFilters.branch_id.value', $branchB->id);

    $stats = $component->instance()->getStats();

    expect($stats[0]['value'])->toBe(number_format(0))
        ->and($stats[1]['value'])->toBe(number_format(0))
        ->and($stats[2]['value'])->toBe(number_format(0));
});
