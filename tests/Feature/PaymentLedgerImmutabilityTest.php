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

    $payment->markReversed($user->id);

    expect($payment->fresh()->reversed_by)->toBe($user->id)
        ->and($payment->fresh()->reversed_at)->not->toBeNull()
        ->and($payment->fresh()->amount)->toEqualWithDelta((float) $payment->amount, 0.01)
        ->and($payment->fresh()->direction)->toBe($payment->direction);
});
