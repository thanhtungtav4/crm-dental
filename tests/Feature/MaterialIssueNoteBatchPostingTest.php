<?php

use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\MaterialIssueItem;
use App\Models\MaterialIssueNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('posts material issue note against a selected batch and inventory ledger', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 12,
        'cost_price' => 22_000,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'BATCH-ISSUE-FLOW-001',
        'expiry_date' => now()->addMonths(8)->toDateString(),
        'quantity' => 12,
        'purchase_price' => 22_000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $issueNote = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Xuat vat tu co truy vet lo',
    ]);

    MaterialIssueItem::query()->create([
        'material_issue_note_id' => $issueNote->id,
        'material_id' => $material->id,
        'material_batch_id' => $batch->id,
        'quantity' => 5,
        'unit_cost' => 22_000,
    ]);

    $issueNote->post($admin->id);

    expect($issueNote->refresh()->status)->toBe(MaterialIssueNote::STATUS_POSTED)
        ->and($material->fresh()->stock_qty)->toBe(7)
        ->and($batch->fresh()->quantity)->toBe(7)
        ->and(InventoryTransaction::query()
            ->where('material_issue_note_id', $issueNote->id)
            ->where('material_id', $material->id)
            ->where('material_batch_id', $batch->id)
            ->where('type', 'out')
            ->exists())->toBeTrue();
});

it('rejects selecting a batch that does not belong to the chosen material', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $materialA = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 10,
    ]);
    $materialB = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 10,
    ]);
    $batchB = MaterialBatch::query()->create([
        'material_id' => $materialB->id,
        'batch_number' => 'BATCH-OTHER-MATERIAL-001',
        'expiry_date' => now()->addMonths(4)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 18_000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $issueNote = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Thu forged payload batch',
    ]);

    expect(fn () => MaterialIssueItem::query()->create([
        'material_issue_note_id' => $issueNote->id,
        'material_id' => $materialA->id,
        'material_batch_id' => $batchB->id,
        'quantity' => 1,
        'unit_cost' => 18_000,
    ]))->toThrow(ValidationException::class, 'Lô vật tư không thuộc vật tư đã chọn.');
});

it('requires batch selection in the issue note relation manager source', function (): void {
    $source = file_get_contents(app_path('Filament/Resources/MaterialIssueNotes/RelationManagers/ItemsRelationManager.php'));

    expect($source)->toContain("Select::make('material_batch_id')")
        ->toContain('Bat buoc chon lo vat tu de truy vet ton kho va han dung.');
});
