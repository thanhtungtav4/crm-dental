<?php

use App\Models\Branch;
use App\Models\MaterialIssueNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('removes destructive material issue note surfaces from the ui', function (): void {
    $editPage = File::get(app_path('Filament/Resources/MaterialIssueNotes/Pages/EditMaterialIssueNote.php'));
    $table = File::get(app_path('Filament/Resources/MaterialIssueNotes/Tables/MaterialIssueNotesTable.php'));

    expect($editPage)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('DeleteAction');

    expect($table)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('DeleteBulkAction');
});

it('denies material issue note delete restore and force delete via policy even for admin', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $note = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Guard note',
    ]);

    expect($admin->can('delete', $note))->toBeFalse()
        ->and($admin->can('deleteAny', MaterialIssueNote::class))->toBeFalse()
        ->and($admin->can('restore', $note))->toBeFalse()
        ->and($admin->can('forceDelete', $note))->toBeFalse()
        ->and($admin->can('restoreAny', MaterialIssueNote::class))->toBeFalse()
        ->and($admin->can('forceDeleteAny', MaterialIssueNote::class))->toBeFalse();
});

it('blocks direct material issue note delete attempts at model layer', function (): void {
    $branch = Branch::factory()->create();
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $note = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Model guard note',
    ]);

    expect(fn () => $note->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});
