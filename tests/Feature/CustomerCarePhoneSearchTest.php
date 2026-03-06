<?php

use App\Filament\Pages\CustomerCare;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Livewire\Livewire;

it('uses patient phone search hashes in customer care hot path queries', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $matchingCustomer = Customer::factory()->create([
        'phone' => '0901 234 567',
    ]);
    $matchingPatient = Patient::factory()->create([
        'customer_id' => $matchingCustomer->id,
        'first_branch_id' => $matchingCustomer->branch_id,
        'phone' => $matchingCustomer->phone,
    ]);
    $matchingNote = Note::factory()->create([
        'patient_id' => $matchingPatient->id,
        'branch_id' => $matchingPatient->first_branch_id,
        'care_type' => 'general_care',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
    ]);

    $otherCustomer = Customer::factory()->create([
        'phone' => '0909 888 777',
    ]);
    $otherPatient = Patient::factory()->create([
        'customer_id' => $otherCustomer->id,
        'first_branch_id' => $otherCustomer->branch_id,
        'phone' => $otherCustomer->phone,
    ]);
    Note::factory()->create([
        'patient_id' => $otherPatient->id,
        'branch_id' => $otherPatient->first_branch_id,
        'care_type' => 'general_care',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
    ]);

    $page = Livewire::test(CustomerCare::class)->instance();
    $query = Note::query()->whereNotNull('patient_id');

    $results = invokeCustomerCarePhoneSearch($page, $query, '0901234567')->pluck('id');

    expect($results)
        ->toHaveCount(1)
        ->and($results->all())->toBe([$matchingNote->id]);
});

it('returns no result when customer care phone search cannot derive a hash', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = Livewire::test(CustomerCare::class)->instance();
    $query = Note::query()->whereNotNull('patient_id');

    $results = invokeCustomerCarePhoneSearch($page, $query, 'abc')->get();

    expect($results)->toHaveCount(0);
});

function invokeCustomerCarePhoneSearch(CustomerCare $page, \Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder
{
    $method = new ReflectionMethod($page, 'applyPatientPhoneSearch');
    $method->setAccessible(true);

    return $method->invoke($page, $query, $search);
}
