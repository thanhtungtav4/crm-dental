<?php

use App\Filament\Resources\ZnsCampaigns\Pages\CreateZnsCampaign;
use App\Filament\Resources\ZnsCampaigns\Pages\EditZnsCampaign;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Models\ZnsCampaign;
use App\Services\ZnsCampaignWorkflowService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('forces create payload into draft workflow state', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = app(CreateZnsCampaign::class);
    $mutator = function (array $data): array {
        return $this->mutateFormDataBeforeCreate($data);
    };
    $mutator = $mutator->bindTo($page, CreateZnsCampaign::class);

    $mutated = $mutator([
        'name' => 'Forged scheduled campaign',
        'status' => ZnsCampaign::STATUS_CANCELLED,
        'scheduled_at' => now()->addHour(),
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
        'sent_count' => 99,
        'failed_count' => 5,
    ]);

    expect($mutated['status'])->toBe(ZnsCampaign::STATUS_DRAFT)
        ->and($mutated['scheduled_at'])->toBeNull()
        ->and($mutated['started_at'])->toBeNull()
        ->and($mutated['finished_at'])->toBeNull()
        ->and($mutated['sent_count'])->toBe(0)
        ->and($mutated['failed_count'])->toBe(0);
});

it('blocks forged edit payload from changing campaign status directly', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Editable campaign',
        'status' => ZnsCampaign::STATUS_DRAFT,
    ]);

    $page = app(EditZnsCampaign::class);
    $page->record = $campaign;

    $mutator = function (array $data): array {
        return $this->mutateFormDataBeforeSave($data);
    };
    $mutator = $mutator->bindTo($page, EditZnsCampaign::class);

    expect(fn () => $mutator([
        'name' => 'Edited campaign',
        'status' => ZnsCampaign::STATUS_CANCELLED,
    ]))->toThrow(ValidationException::class, 'ZnsCampaignWorkflowService');
});

it('blocks direct workflow controlled campaign mutations outside workflow service', function (): void {
    $campaign = ZnsCampaign::query()->create([
        'name' => 'Guarded campaign',
        'status' => ZnsCampaign::STATUS_DRAFT,
    ]);

    expect(fn () => $campaign->update([
        'status' => ZnsCampaign::STATUS_SCHEDULED,
    ]))->toThrow(ValidationException::class, 'ZnsCampaignWorkflowService');

    expect(fn () => $campaign->fresh()->update([
        'scheduled_at' => now()->addMinutes(15),
    ]))->toThrow(ValidationException::class, 'Workflow campaign ZNS');
});

it('routes campaign transitions through workflow service with audit trail', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Workflow campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_DRAFT,
    ]);

    $workflow = app(ZnsCampaignWorkflowService::class);

    $scheduledCampaign = $workflow->schedule($campaign, now()->addHour(), 'Len lich gui toi');
    $runningCampaign = $workflow->runNow($scheduledCampaign, 'Can gui gap');
    $cancelledCampaign = $workflow->cancel($runningCampaign, 'Dung campaign do doi noi dung');

    expect($scheduledCampaign->status)->toBe(ZnsCampaign::STATUS_SCHEDULED)
        ->and($scheduledCampaign->scheduled_at)->not->toBeNull()
        ->and($runningCampaign->status)->toBe(ZnsCampaign::STATUS_RUNNING)
        ->and($runningCampaign->started_at)->not->toBeNull()
        ->and($cancelledCampaign->status)->toBe(ZnsCampaign::STATUS_CANCELLED)
        ->and($cancelledCampaign->finished_at)->not->toBeNull();

    $logs = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('entity_id', $campaign->id)
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(3)
        ->and($logs[0]->action)->toBe(AuditLog::ACTION_UPDATE)
        ->and($logs[0]->metadata['reason'] ?? null)->toBe('Len lich gui toi')
        ->and($logs[0]->metadata['status_to'] ?? null)->toBe(ZnsCampaign::STATUS_SCHEDULED)
        ->and($logs[1]->action)->toBe(AuditLog::ACTION_RUN)
        ->and($logs[1]->metadata['reason'] ?? null)->toBe('Can gui gap')
        ->and($logs[1]->metadata['status_to'] ?? null)->toBe(ZnsCampaign::STATUS_RUNNING)
        ->and($logs[2]->action)->toBe(AuditLog::ACTION_CANCEL)
        ->and($logs[2]->metadata['reason'] ?? null)->toBe('Dung campaign do doi noi dung')
        ->and($logs[2]->metadata['status_to'] ?? null)->toBe(ZnsCampaign::STATUS_CANCELLED);
});

it('wires zns resource surfaces through workflow service and removes delete actions', function (): void {
    $createPage = File::get(app_path('Filament/Resources/ZnsCampaigns/Pages/CreateZnsCampaign.php'));
    $editPage = File::get(app_path('Filament/Resources/ZnsCampaigns/Pages/EditZnsCampaign.php'));
    $table = File::get(app_path('Filament/Resources/ZnsCampaigns/Tables/ZnsCampaignsTable.php'));
    $form = File::get(app_path('Filament/Resources/ZnsCampaigns/Schemas/ZnsCampaignForm.php'));
    $model = File::get(app_path('Models/ZnsCampaign.php'));
    $service = File::get(app_path('Services/ZnsCampaignWorkflowService.php'));

    expect($createPage)
        ->toContain('ZnsCampaignWorkflowService::class')
        ->toContain('prepareCreatePayload')
        ->toContain('handleRecordCreation');

    expect($editPage)
        ->toContain('ZnsCampaignWorkflowService::class')
        ->toContain('prepareEditablePayload')
        ->toContain("Action::make('schedule')")
        ->toContain("Action::make('runNow')")
        ->toContain("Action::make('cancel')")
        ->not->toContain('DeleteAction::make()');

    expect($table)
        ->toContain('ZnsCampaignWorkflowService::class')
        ->toContain("Action::make('schedule')")
        ->toContain("Action::make('runNow')")
        ->toContain("Action::make('cancel')")
        ->not->toContain('DeleteBulkAction::make()');

    expect($form)
        ->toContain("Select::make('status')")
        ->toContain('->dehydrated(false)')
        ->toContain("DateTimePicker::make('scheduled_at')")
        ->toContain('Dùng action "Lên lịch" để cập nhật thời gian chạy.');

    expect($model)
        ->toContain('runWithinManagedWorkflow')
        ->toContain('Trang thai campaign ZNS chi duoc thay doi qua ZnsCampaignWorkflowService.')
        ->toContain('Campaign ZNS khong ho tro xoa. Vui long huy campaign qua workflow.');

    expect($service)
        ->toContain('lockForUpdate()')
        ->toContain('AuditLog::ACTION_CANCEL')
        ->toContain('syncSummaryStatus');
});
