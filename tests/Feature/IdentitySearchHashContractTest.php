<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsCampaignDelivery;
use App\Services\ZnsPayloadSanitizer;
use App\Support\IdentitySearchHash;

it('builds deterministic scoped hashes while keeping domains isolated', function (): void {
    expect(IdentitySearchHash::phone('customer', '+84 901 222 333'))
        ->toBe(hash('sha256', 'customer-phone|0901222333'))
        ->and(IdentitySearchHash::phone('patient', '0901222333'))
        ->toBe(hash('sha256', 'patient-phone|0901222333'))
        ->and(IdentitySearchHash::email('customer', ' Lead@Example.com '))
        ->toBe(hash('sha256', 'customer-email|lead@example.com'))
        ->and(IdentitySearchHash::email('patient', 'lead@example.com'))
        ->toBe(hash('sha256', 'patient-email|lead@example.com'))
        ->and(IdentitySearchHash::phone('customer', '0901222333'))
        ->not->toBe(IdentitySearchHash::phone('patient', '0901222333'));
});

it('keeps customer patient and zns hash lanes aligned on the shared contract', function (): void {
    $phone = '+84 901 555 666';

    expect(Customer::phoneSearchHash($phone))
        ->toBe(IdentitySearchHash::phone('customer', $phone))
        ->and(Patient::phoneSearchHash($phone))
        ->toBe(IdentitySearchHash::phone('patient', $phone))
        ->and(ZnsAutomationEvent::phoneSearchHash($phone))
        ->toBe(IdentitySearchHash::phone('zns', $phone))
        ->and(ZnsCampaignDelivery::phoneSearchHash($phone))
        ->toBe(IdentitySearchHash::phone('zns', $phone))
        ->and(ZnsPayloadSanitizer::phoneSearchHash($phone))
        ->toBe(IdentitySearchHash::phone('zns', $phone));
});

it('reuses customer and patient contact scopes for normalized lookups', function (): void {
    $branch = Branch::factory()->create();

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0901222333',
        'email' => 'scope-target@example.test',
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'phone' => '0901222333',
        'email' => 'scope-target@example.test',
    ]);

    expect(Customer::query()->wherePhoneMatches('+84 901 222 333')->value('id'))
        ->toBe($customer->id)
        ->and(Customer::query()->whereEmailMatches(' Scope-Target@example.test ')->value('id'))
        ->toBe($customer->id)
        ->and(Patient::query()->wherePhoneMatches('0901 222 333')->value('id'))
        ->toBe($patient->id)
        ->and(Patient::query()->whereEmailMatches('scope-target@example.test')->value('id'))
        ->toBe($patient->id);
});

it('keeps patient phone uniqueness scoped by branch through the shared query contract', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create([
        'branch_id' => $branchA->id,
        'phone' => '0901444555',
    ]);

    $customerB = Customer::factory()->create([
        'branch_id' => $branchB->id,
        'phone' => '0901444555',
    ]);

    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'phone' => '0901444555',
    ]);

    Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'phone' => '0901444555',
    ]);

    expect(Patient::query()->wherePhoneMatchesInBranch('0901 444 555', $branchA->id)->value('id'))
        ->toBe($patientA->id)
        ->and(Patient::query()->wherePhoneMatchesInBranch('0901 444 555', 999999)->exists())
        ->toBeFalse();
});
