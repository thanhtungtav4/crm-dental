<?php

namespace App\Services;

use App\Models\Consent;
use Illuminate\Support\Facades\DB;

class ConsentLifecycleService
{
    public function sign(Consent $consent, int $signedBy, array $signatureContext = []): Consent
    {
        return DB::transaction(function () use ($consent, $signedBy, $signatureContext): Consent {
            $lockedConsent = Consent::query()
                ->lockForUpdate()
                ->findOrFail($consent->id);

            $lockedConsent->fill([
                'status' => Consent::STATUS_SIGNED,
                'signed_by' => $signedBy,
                'signed_at' => $lockedConsent->signed_at ?? now(),
                'signature_context' => $signatureContext !== [] ? $signatureContext : $lockedConsent->signature_context,
            ]);
            $lockedConsent->save();

            return $lockedConsent->fresh();
        }, 3);
    }

    public function revoke(Consent $consent): Consent
    {
        return DB::transaction(function () use ($consent): Consent {
            $lockedConsent = Consent::query()
                ->lockForUpdate()
                ->findOrFail($consent->id);

            $lockedConsent->fill([
                'status' => Consent::STATUS_REVOKED,
                'revoked_at' => $lockedConsent->revoked_at ?? now(),
            ]);
            $lockedConsent->save();

            return $lockedConsent->fresh();
        }, 3);
    }

    public function expire(Consent $consent): Consent
    {
        return DB::transaction(function () use ($consent): Consent {
            $lockedConsent = Consent::query()
                ->lockForUpdate()
                ->findOrFail($consent->id);

            $lockedConsent->status = Consent::STATUS_EXPIRED;
            $lockedConsent->save();

            return $lockedConsent->fresh();
        }, 3);
    }
}
