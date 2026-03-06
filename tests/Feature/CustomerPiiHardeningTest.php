<?php

use App\Models\Branch;
use App\Models\Customer;

it('encrypts customer pii and persists searchable hashes', function (): void {
    $branch = Branch::factory()->create();

    $customer = Customer::query()->create([
        'branch_id' => $branch->id,
        'full_name' => 'Tran Thi PII',
        'phone' => '0901234567',
        'email' => 'pii@example.test',
        'address' => '123 Nguyen Trai, Quan 1',
        'source' => 'website',
        'status' => 'lead',
    ]);

    $customer->refresh();

    expect($customer->phone)->toBe('0901234567')
        ->and($customer->email)->toBe('pii@example.test')
        ->and($customer->address)->toBe('123 Nguyen Trai, Quan 1')
        ->and($customer->phone_search_hash)->toBe(Customer::phoneSearchHash('0901234567'))
        ->and($customer->email_search_hash)->toBe(Customer::emailSearchHash('pii@example.test'))
        ->and($customer->phone_normalized)->toBeNull()
        ->and($customer->getRawOriginal('phone'))->not->toBe('0901234567')
        ->and($customer->getRawOriginal('email'))->not->toBe('pii@example.test')
        ->and($customer->getRawOriginal('address'))->not->toBe('123 Nguyen Trai, Quan 1');
});

it('normalizes equivalent phone formats to the same customer search hash', function (): void {
    expect(Customer::phoneSearchHash('0901234567'))
        ->toBe(Customer::phoneSearchHash('+84 901 234 567'))
        ->and(Customer::phoneSearchHash('0901234567'))
        ->not->toBeNull();
});
