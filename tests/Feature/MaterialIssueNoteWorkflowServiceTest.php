<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\MaterialIssueItem;
use App\Models\MaterialIssueNote;
use App\Models\Patient;
use App\Models\User;
use App\Services\MaterialIssueNoteWorkflowService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('posts material issue notes through the canonical workflow service and records audit metadata', function (): void {
    [$issueNote, $material, $batch, $admin] = makeMaterialIssueWorkflowFixture();
    $this->actingAs($admin);

    $warnings = app(MaterialIssueNoteWorkflowService::class)->post(
        $issueNote,
        'operator_post_inventory',
        $admin->id,
    );

    $issueNote->refresh();

    expect($issueNote->status)->toBe(MaterialIssueNote::STATUS_POSTED)
        ->and($issueNote->posted_by)->toBe($admin->id)
        ->and($issueNote->posted_at)->not->toBeNull()
        ->and($material->refresh()->stock_qty)->toBe(6)
        ->and($batch->refresh()->quantity)->toBe(6)
        ->and($warnings)->toHaveCount(1)
        ->and(InventoryTransaction::query()
            ->where('material_issue_note_id', $issueNote->id)
            ->where('material_id', $material->id)
            ->where('material_batch_id', $batch->id)
            ->where('type', 'out')
            ->count())->toBe(1);

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_MATERIAL_ISSUE_NOTE)
        ->where('entity_id', $issueNote->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog?->actor_id)->toBe($admin->id)
        ->and($auditLog?->patient_id)->toBe($issueNote->patient_id)
        ->and($auditLog?->branch_id)->toBe($issueNote->branch_id)
        ->and($auditLog?->metadata)->toMatchArray([
            'status_from' => MaterialIssueNote::STATUS_DRAFT,
            'status_to' => MaterialIssueNote::STATUS_POSTED,
            'reason' => 'operator_post_inventory',
            'trigger' => 'manual_post',
            'material_issue_note_id' => $issueNote->id,
            'note_no' => $issueNote->note_no,
            'patient_id' => $issueNote->patient_id,
            'branch_id' => $issueNote->branch_id,
            'item_count' => 1,
            'posted_by' => $admin->id,
        ])
        ->and(data_get($auditLog, 'metadata.posted_at'))->not->toBeNull();
});

it('cancels draft material issue notes through the workflow service and records structured audit metadata', function (): void {
    [$issueNote, , , $admin] = makeMaterialIssueWorkflowFixture();
    $this->actingAs($admin);

    $cancelled = app(MaterialIssueNoteWorkflowService::class)->cancel(
        $issueNote,
        'stock_request_cancelled',
        $admin->id,
    );

    expect($cancelled->status)->toBe(MaterialIssueNote::STATUS_CANCELLED)
        ->and($cancelled->posted_at)->toBeNull()
        ->and($cancelled->posted_by)->toBeNull();

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_MATERIAL_ISSUE_NOTE)
        ->where('entity_id', $issueNote->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog?->metadata)->toMatchArray([
            'status_from' => MaterialIssueNote::STATUS_DRAFT,
            'status_to' => MaterialIssueNote::STATUS_CANCELLED,
            'reason' => 'stock_request_cancelled',
            'trigger' => 'manual_cancel',
            'material_issue_note_id' => $issueNote->id,
            'note_no' => $issueNote->note_no,
            'item_count' => 1,
        ]);
});

it('blocks raw status changes outside the workflow service for material issue notes', function (): void {
    [$issueNote, , , $admin] = makeMaterialIssueWorkflowFixture();
    $this->actingAs($admin);

    expect(fn () => $issueNote->update([
        'status' => MaterialIssueNote::STATUS_POSTED,
    ]))->toThrow(ValidationException::class, 'Trang thai phieu xuat chi duoc thay doi qua MaterialIssueNoteWorkflowService.');
});

it('wires material issue note create edit and table surfaces through the workflow contract', function (): void {
    $createPage = File::get(app_path('Filament/Resources/MaterialIssueNotes/Pages/CreateMaterialIssueNote.php'));
    $editPage = File::get(app_path('Filament/Resources/MaterialIssueNotes/Pages/EditMaterialIssueNote.php'));
    $table = File::get(app_path('Filament/Resources/MaterialIssueNotes/Tables/MaterialIssueNotesTable.php'));
    $form = File::get(app_path('Filament/Resources/MaterialIssueNotes/Schemas/MaterialIssueNoteForm.php'));
    $model = File::get(app_path('Models/MaterialIssueNote.php'));
    $service = File::get(app_path('Services/MaterialIssueNoteWorkflowService.php'));

    expect($createPage)
        ->toContain('MaterialIssueNoteWorkflowService::class')
        ->toContain('prepareCreatePayload');

    expect($editPage)
        ->toContain('MaterialIssueNoteWorkflowService::class')
        ->toContain('prepareEditablePayload')
        ->toContain("Action::make('post')")
        ->toContain("Action::make('cancel')");

    expect($table)
        ->toContain('MaterialIssueNoteWorkflowService::class')
        ->toContain("Action::make('post')")
        ->toContain("Action::make('cancel')");

    expect($form)
        ->toContain("Select::make('status')")
        ->toContain('Xuất kho / Hủy phiếu');

    expect($model)
        ->toContain('MaterialIssueNoteWorkflowService::class')
        ->toContain('runWithinManagedWorkflow');

    expect($service)
        ->toContain('InventoryMutationService::class')
        ->toContain('AuditLog::ENTITY_MATERIAL_ISSUE_NOTE');
});

/**
 * @return array{0: MaterialIssueNote, 1: Material, 2: MaterialBatch, 3: User}
 */
function makeMaterialIssueWorkflowFixture(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 10,
        'min_stock' => 7,
        'cost_price' => 50_000,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'BATCH-WORKFLOW-001',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 50_000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $issueNote = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Xuất vật tư workflow',
    ]);

    MaterialIssueItem::query()->create([
        'material_issue_note_id' => $issueNote->id,
        'material_id' => $material->id,
        'material_batch_id' => $batch->id,
        'quantity' => 4,
        'unit_cost' => 50_000,
    ]);

    return [$issueNote, $material, $batch, $admin];
}
