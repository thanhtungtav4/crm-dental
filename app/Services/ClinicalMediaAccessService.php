<?php

namespace App\Services;

use App\Models\ClinicalMediaAccessLog;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class ClinicalMediaAccessService
{
    public function __construct(protected PhiAccessAuditService $phiAccessAuditService) {}

    public function signedViewUrl(ClinicalMediaAsset $asset, ?Carbon $expiresAt = null): string
    {
        $expires = $expiresAt ?? now()->addMinutes(ClinicRuntimeSettings::clinicalMediaSignedUrlTtlMinutes());

        return URL::temporarySignedRoute(
            'clinical-media.view',
            $expires,
            ['clinicalMediaAsset' => (int) $asset->id],
        );
    }

    public function signedDownloadUrl(ClinicalMediaAsset $asset, ?Carbon $expiresAt = null): string
    {
        $expires = $expiresAt ?? now()->addMinutes(ClinicRuntimeSettings::clinicalMediaSignedUrlTtlMinutes());

        return URL::temporarySignedRoute(
            'clinical-media.download',
            $expires,
            ['clinicalMediaAsset' => (int) $asset->id],
        );
    }

    /**
     * @return array{view_url:string, download_url:string, expires_at:string}
     */
    public function sharePayload(ClinicalMediaAsset $asset, ?Carbon $expiresAt = null): array
    {
        $expires = $expiresAt ?? now()->addMinutes(ClinicRuntimeSettings::clinicalMediaSignedUrlTtlMinutes());

        return [
            'view_url' => $this->signedViewUrl($asset, $expires),
            'download_url' => $this->signedDownloadUrl($asset, $expires),
            'expires_at' => $expires->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordAction(
        ClinicalMediaAsset $asset,
        string $action,
        ?ClinicalMediaVersion $version = null,
        ?string $purpose = null,
        array $context = [],
    ): ClinicalMediaAccessLog {
        $log = ClinicalMediaAccessLog::query()->create([
            'clinical_media_asset_id' => (int) $asset->id,
            'clinical_media_version_id' => $version?->id,
            'patient_id' => $asset->resolvePatientId(),
            'visit_episode_id' => $asset->visit_episode_id,
            'branch_id' => $asset->resolveBranchId(),
            'actor_id' => auth()->id(),
            'action' => $action,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'purpose' => $purpose,
            'context' => $context,
            'occurred_at' => now(),
        ]);

        $this->phiAccessAuditService->recordClinicalMediaAccess(
            asset: $asset,
            action: $action,
            context: array_merge($context, [
                'clinical_media_asset_id' => (int) $asset->id,
                'clinical_media_version_id' => $version?->id,
                'purpose' => $purpose,
            ]),
        );

        return $log;
    }

    public function originalVersion(ClinicalMediaAsset $asset): ?ClinicalMediaVersion
    {
        return $asset->versions()
            ->where('is_original', true)
            ->latest('id')
            ->first();
    }
}
