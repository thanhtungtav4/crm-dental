<?php

use App\Models\Branch;
use App\Models\ClinicalOrder;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('blocks direct clinical order delete attempts at model layer', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $order = ClinicalOrder::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'ordered_by' => $doctor->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_PENDING,
    ]);

    expect(fn () => $order->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});
