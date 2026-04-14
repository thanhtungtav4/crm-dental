<?php

use App\Filament\Resources\MaterialBatches\MaterialBatchResource;
use App\Filament\Resources\Materials\MaterialResource;
use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\MaterialIssueItem;
use App\Models\MaterialIssueNote;
use App\Models\Patient;
use App\Models\PatientWallet;
use App\Models\Payment;
use App\Models\PopupAnnouncement;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Policies\MaterialBatchPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\TreatmentMaterialPolicy;
use App\Services\PatientWalletService;
use App\Services\TreatmentMaterialUsageService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

it('enforces branch isolation for material and material batch in policy and resource queries', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $managerA = User::factory()->create(['branch_id' => $branchA->id]);
    $managerA->assignRole('Manager');

    $materialA = Material::factory()->create(['branch_id' => $branchA->id]);
    $materialB = Material::factory()->create(['branch_id' => $branchB->id]);

    $batchA = MaterialBatch::query()->create([
        'material_id' => $materialA->id,
        'batch_number' => 'BATCH-A-001',
        'expiry_date' => now()->addYear()->toDateString(),
        'quantity' => 20,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);
    $batchB = MaterialBatch::query()->create([
        'material_id' => $materialB->id,
        'batch_number' => 'BATCH-B-001',
        'expiry_date' => now()->addYear()->toDateString(),
        'quantity' => 20,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $materialPolicy = app(MaterialPolicy::class);
    $materialBatchPolicy = app(MaterialBatchPolicy::class);

    expect($materialPolicy->view($managerA, $materialA))->toBeTrue()
        ->and($materialPolicy->view($managerA, $materialB))->toBeFalse()
        ->and($materialBatchPolicy->view($managerA, $batchA))->toBeTrue()
        ->and($materialBatchPolicy->view($managerA, $batchB))->toBeFalse();

    $this->actingAs($managerA);

    expect(MaterialResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$materialA->id])
        ->not->toContain($materialB->id);

    expect(MaterialBatchResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$batchA->id])
        ->not->toContain($batchB->id);
});

it('posts material issue note only once when post is retried', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 8,
        'min_stock' => 2,
        'cost_price' => 40_000,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'BATCH-ISSUE-001',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity' => 8,
        'purchase_price' => 40_000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $issueNote = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Xuất kho kiểm thử idempotency',
    ]);

    MaterialIssueItem::query()->create([
        'material_issue_note_id' => $issueNote->id,
        'material_id' => $material->id,
        'material_batch_id' => $batch->id,
        'quantity' => 3,
        'unit_cost' => 40_000,
    ]);

    $issueNote->post($admin->id);
    $issueNote->post($admin->id);

    expect($issueNote->refresh()->status)->toBe(MaterialIssueNote::STATUS_POSTED)
        ->and($material->refresh()->stock_qty)->toBe(5)
        ->and($batch->refresh()->quantity)->toBe(5)
        ->and(InventoryTransaction::query()
            ->where('material_issue_note_id', $issueNote->id)
            ->where('material_id', $material->id)
            ->where('material_batch_id', $batch->id)
            ->where('type', 'out')
            ->count())->toBe(1);
});

it('prevents editing or deleting material issue items after note is posted', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 10,
        'cost_price' => 35_000,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'BATCH-ISSUE-002',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 35_000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $issueNote = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Khoá sau posted',
    ]);

    $item = MaterialIssueItem::query()->create([
        'material_issue_note_id' => $issueNote->id,
        'material_id' => $material->id,
        'material_batch_id' => $batch->id,
        'quantity' => 2,
        'unit_cost' => 35_000,
    ]);

    $issueNote->post($admin->id);

    expect(fn () => $item->update(['quantity' => 1]))
        ->toThrow(ValidationException::class, 'Phiếu đã xuất kho không thể cập nhật vật tư.');

    expect(fn () => $item->delete())
        ->toThrow(ValidationException::class, 'Phiếu đã xuất kho không thể xóa vật tư.');

    expect(fn () => $issueNote->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});

it('forces popup announcements through cancel workflow instead of direct delete', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup hardening baseline',
        'message' => 'Popup khong duoc xoa truc tiep',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['Manager'],
        'target_branch_ids' => [],
        'starts_at' => now()->subMinute(),
        'published_at' => now()->subMinute(),
    ]);

    $announcement->cancel('baseline_cancel');

    expect($announcement->fresh()->status)->toBe(PopupAnnouncement::STATUS_CANCELLED)
        ->and(fn () => $announcement->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});

it('blocks treatment material updates to protect stock consistency', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
    ]);
    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $manager->id,
    ]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 12,
        'cost_price' => 25_000,
    ]);

    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'BATCH-TREAT-001',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity' => 12,
        'purchase_price' => 25_000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 2,
        'used_by' => $manager->id,
    ]);

    expect(fn () => $usage->update(['quantity' => 1]))
        ->toThrow(ValidationException::class, 'Khong ho tro chinh sua vat tu da ghi nhan');

    $policy = app(TreatmentMaterialPolicy::class);

    expect($policy->update($manager, $usage))->toBeFalse()
        ->and($policy->delete($manager, $usage))->toBeFalse()
        ->and($material->refresh()->stock_qty)->toBe(10);
});

it('keeps wallet ledger idempotent when postPayment is retried', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'total_amount' => 2_000_000,
        'status' => Invoice::STATUS_ISSUED,
    ]);

    $receiver = User::factory()->create(['branch_id' => $branch->id]);
    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 500_000,
        'direction' => 'receipt',
        'is_deposit' => true,
        'method' => 'cash',
        'payment_source' => 'patient',
        'paid_at' => now(),
        'received_by' => $receiver->id,
    ]);

    $walletService = app(PatientWalletService::class);
    $walletService->postPayment($payment);
    $walletService->postPayment($payment);

    $wallet = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();

    expect(WalletLedgerEntry::query()
        ->where('payment_id', $payment->id)
        ->where('entry_type', 'deposit')
        ->count())->toBe(1)
        ->and((float) $wallet->balance)->toEqualWithDelta(500_000, 0.01);
});

it('applies strict throttle to mobile auth token endpoint', function (): void {
    $route = app('router')->getRoutes()->getByName('api.v1.mobile.auth.token');

    expect($route?->middleware())->toContain('throttle:mobile-auth');

    $email = 'throttle.mobile@example.com';
    $ipAddress = '127.0.0.1';
    $emailLimiterKey = 'mobile-auth:email:'.sha1(strtolower($email).'|'.$ipAddress);

    RateLimiter::clear('mobile-auth:ip:'.$ipAddress);
    RateLimiter::clear($emailLimiterKey);

    $this->withServerVariables(['REMOTE_ADDR' => $ipAddress]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/v1/mobile/auth/token', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/mobile/auth/token', [
        'email' => $email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
