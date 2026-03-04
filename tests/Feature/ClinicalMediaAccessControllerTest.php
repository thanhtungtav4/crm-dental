<?php

use App\Models\Branch;
use App\Models\ClinicalMediaAccessLog;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\Customer;
use App\Models\EmrAuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

it('serves signed clinical media view URL and records access audit logs', function (): void {
    Storage::fake('public');

    $context = seedClinicalMediaHttpContext();
    $asset = $context['asset'];
    $user = $context['user'];

    $this->actingAs($user);

    $url = URL::temporarySignedRoute(
        'clinical-media.view',
        now()->addMinutes(5),
        ['clinicalMediaAsset' => $asset->id],
    );

    $this->get($url)->assertOk();

    $accessLog = ClinicalMediaAccessLog::query()
        ->where('clinical_media_asset_id', $asset->id)
        ->where('action', ClinicalMediaAccessLog::ACTION_VIEW)
        ->latest('id')
        ->first();

    expect($accessLog)->not->toBeNull()
        ->and((int) $accessLog?->actor_id)->toBe((int) $user->id);

    $phiAudit = EmrAuditLog::query()
        ->where('entity_type', EmrAuditLog::ENTITY_PHI_ACCESS)
        ->where('entity_id', $asset->id)
        ->where('action', EmrAuditLog::ACTION_READ)
        ->latest('id')
        ->first();

    expect($phiAudit)->not->toBeNull()
        ->and((string) data_get($phiAudit?->context, 'resource'))->toBe('clinical_media_asset')
        ->and((string) data_get($phiAudit?->context, 'action'))->toBe(ClinicalMediaAccessLog::ACTION_VIEW);
});

it('returns signed share payload and logs share action', function (): void {
    Storage::fake('public');

    $context = seedClinicalMediaHttpContext();
    $asset = $context['asset'];
    $user = $context['user'];

    $this->actingAs($user);

    $response = $this->postJson(route('clinical-media.share', ['clinicalMediaAsset' => $asset->id]), [
        'purpose' => 'claim-review',
        'recipient_hint' => 'insurance',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure([
            'ok',
            'data' => [
                'view_url',
                'download_url',
                'expires_at',
            ],
        ]);

    $accessLog = ClinicalMediaAccessLog::query()
        ->where('clinical_media_asset_id', $asset->id)
        ->where('action', ClinicalMediaAccessLog::ACTION_SHARE)
        ->latest('id')
        ->first();

    expect($accessLog)->not->toBeNull()
        ->and((string) data_get($accessLog?->context, 'recipient_hint'))->toBe('insurance');
});

it('downloads signed clinical media and records download action', function (): void {
    Storage::fake('public');

    $context = seedClinicalMediaHttpContext();
    $asset = $context['asset'];
    $user = $context['user'];

    $this->actingAs($user);

    $url = URL::temporarySignedRoute(
        'clinical-media.download',
        now()->addMinutes(5),
        ['clinicalMediaAsset' => $asset->id],
    );

    $response = $this->get($url);
    $response->assertOk();

    $accessLog = ClinicalMediaAccessLog::query()
        ->where('clinical_media_asset_id', $asset->id)
        ->where('action', ClinicalMediaAccessLog::ACTION_DOWNLOAD)
        ->latest('id')
        ->first();

    expect($accessLog)->not->toBeNull()
        ->and((int) $accessLog?->actor_id)->toBe((int) $user->id);
});

it('rejects unsigned clinical media URL', function (): void {
    Storage::fake('public');

    $context = seedClinicalMediaHttpContext();
    $asset = $context['asset'];
    $user = $context['user'];

    $this->actingAs($user);

    $this->get(route('clinical-media.view', ['clinicalMediaAsset' => $asset->id]))
        ->assertForbidden();
});

/**
 * @return array{user: User, asset: ClinicalMediaAsset}
 */
function seedClinicalMediaHttpContext(): array
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $user->assignRole('Admin');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $path = 'clinical-media/http-'.$patient->id.'.jpg';
    Storage::disk('public')->put($path, 'binary-http-test');

    $asset = ClinicalMediaAsset::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'captured_by' => $user->id,
        'captured_at' => now(),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => strlen('binary-http-test'),
        'checksum_sha256' => hash('sha256', 'binary-http-test'),
        'storage_disk' => 'public',
        'storage_path' => $path,
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ]);

    ClinicalMediaVersion::query()->create([
        'clinical_media_asset_id' => $asset->id,
        'version_number' => 1,
        'is_original' => true,
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => strlen('binary-http-test'),
        'checksum_sha256' => hash('sha256', 'binary-http-test'),
        'storage_disk' => 'public',
        'storage_path' => $path,
        'created_by' => $user->id,
    ]);

    return [
        'user' => $user,
        'asset' => $asset,
    ];
}
