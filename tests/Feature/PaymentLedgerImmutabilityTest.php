<?php

use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('prevents editing posted payments', function () {
    $payment = Payment::factory()->create();

    expect(fn () => $payment->update(['note' => 'Điều chỉnh ghi chú']))
        ->toThrow(ValidationException::class);
});

it('prevents deleting posted payments', function () {
    $payment = Payment::factory()->create();

    expect(fn () => $payment->delete())
        ->toThrow(ValidationException::class);
});

it('marks reversal metadata without editing financial fields', function () {
    $payment = Payment::factory()->create();
    $user = User::factory()->create();

    $reversedPayment = $payment->markReversed($user->id);

    expect($reversedPayment)->toBeInstanceOf(Payment::class)
        ->and($reversedPayment->is($payment))->toBeTrue()
        ->and($reversedPayment->reversed_by)->toBe($user->id)
        ->and($reversedPayment->reversed_at)->not->toBeNull()
        ->and($reversedPayment->amount)->toEqualWithDelta((float) $payment->amount, 0.01)
        ->and($reversedPayment->direction)->toBe($payment->direction);
});
