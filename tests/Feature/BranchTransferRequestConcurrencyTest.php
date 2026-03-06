<?php

use App\Models\Branch;
use App\Models\BranchTransferRequest;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientBranchTransferService;
use Illuminate\Validation\ValidationException;

it('prevents duplicate pending transfer requests for the same patient and target branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $manager->assignRole('Manager');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $this->actingAs($manager);

    $firstRequest = app(PatientBranchTransferService::class)->requestTransfer(
        patient: $patient,
        toBranchId: $branchB->id,
        actorId: $manager->id,
        reason: 'Dieu phoi tiep nhan',
        note: 'Yeu cau dau tien',
    );

    expect($firstRequest->status)->toBe(BranchTransferRequest::STATUS_PENDING);

    expect(fn () => app(PatientBranchTransferService::class)->requestTransfer(
        patient: $patient->fresh(),
        toBranchId: $branchB->id,
        actorId: $manager->id,
        reason: 'Thu lai request',
        note: 'Khong duoc tao duplicate',
    ))->toThrow(ValidationException::class, 'đang chờ xử lý');

    expect(BranchTransferRequest::query()
        ->where('patient_id', $patient->id)
        ->where('to_branch_id', $branchB->id)
        ->pending()
        ->count())->toBe(1);
});

it('blocks rejecting transfer requests that have already been applied', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $manager->assignRole('Manager');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $this->actingAs($manager);

    $request = app(PatientBranchTransferService::class)->requestTransfer(
        patient: $patient,
        toBranchId: $branchB->id,
        actorId: $manager->id,
        reason: 'Chuyen noi tiep nhan',
        note: null,
    );

    $appliedRequest = app(PatientBranchTransferService::class)->applyTransferRequest($request, $manager->id);

    expect($appliedRequest->status)->toBe(BranchTransferRequest::STATUS_APPLIED);

    expect(fn () => app(PatientBranchTransferService::class)->rejectTransferRequest(
        transferRequest: $appliedRequest,
        actorId: $manager->id,
        note: 'Khong duoc reject sau khi da apply',
    ))->toThrow(ValidationException::class, 'không còn ở trạng thái chờ xử lý');
});

it('blocks applying transfer requests that have already been rejected', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $manager->assignRole('Manager');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $this->actingAs($manager);

    $request = app(PatientBranchTransferService::class)->requestTransfer(
        patient: $patient,
        toBranchId: $branchB->id,
        actorId: $manager->id,
        reason: 'Can xet duyet noi bo',
        note: 'request pending',
    );

    $rejectedRequest = app(PatientBranchTransferService::class)->rejectTransferRequest(
        transferRequest: $request,
        actorId: $manager->id,
        note: 'Tu choi do branch khong con cho',
    );

    expect($rejectedRequest->status)->toBe(BranchTransferRequest::STATUS_REJECTED)
        ->and((int) $rejectedRequest->decided_by)->toBe($manager->id)
        ->and((string) $rejectedRequest->note)->toContain('Tu choi do branch khong con cho');

    expect(fn () => app(PatientBranchTransferService::class)->applyTransferRequest(
        transferRequest: $rejectedRequest,
        actorId: $manager->id,
    ))->toThrow(ValidationException::class, 'không còn ở trạng thái chờ xử lý');
});
