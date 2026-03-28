<?php

namespace App\Services;

use App\Models\Consent;
use Illuminate\Support\Facades\DB;

class ConsentLifecycleService
{
    public function sign(
        Consent $consent,
        int $signedBy,
        array $signatureContext = [],
        ?string $reason = null,
        string $trigger = 'manual_sign',
    ): Consent {
        return DB::transaction(function () use ($consent, $signedBy, $signatureContext, $reason, $trigger): Consent {
            $lockedConsent = Consent::query()
                ->lockForUpdate()
                ->findOrFail($consent->id);

            Consent::runWithinManagedWorkflow(function () use ($lockedConsent, $signedBy, $signatureContext): void {
                $lockedConsent->fill([
                    'status' => Consent::STATUS_SIGNED,
                    'signed_by' => $signedBy,
                    'signed_at' => $lockedConsent->signed_at ?? now(),
                    'signature_context' => $signatureContext !== [] ? $signatureContext : $lockedConsent->signature_context,
                ]);
                $lockedConsent->save();
            }, array_filter([
                'actor_id' => $signedBy,
                'reason' => $reason,
                'trigger' => $trigger,
                'signature_source' => data_get($signatureContext, 'source'),
            ], static fn (mixed $value): bool => $value !== null));

            return $lockedConsent->fresh();
        }, 3);
    }

    public function revoke(
        Consent $consent,
        ?string $reason = null,
        ?int $actorId = null,
        string $trigger = 'manual_revoke',
    ): Consent {
        return DB::transaction(function () use ($consent, $reason, $actorId, $trigger): Consent {
            $lockedConsent = Consent::query()
                ->lockForUpdate()
                ->findOrFail($consent->id);

            Consent::runWithinManagedWorkflow(function () use ($lockedConsent): void {
                $lockedConsent->fill([
                    'status' => Consent::STATUS_REVOKED,
                    'revoked_at' => $lockedConsent->revoked_at ?? now(),
                ]);
                $lockedConsent->save();
            }, array_filter([
                'actor_id' => $this->resolveActorId($actorId, $lockedConsent),
                'reason' => $reason,
                'trigger' => $trigger,
            ], static fn (mixed $value): bool => $value !== null));

            return $lockedConsent->fresh();
        }, 3);
    }

    public function expire(
        Consent $consent,
        ?string $reason = null,
        ?int $actorId = null,
        string $trigger = 'auto_expire',
    ): Consent {
        return DB::transaction(function () use ($consent, $reason, $actorId, $trigger): Consent {
            $lockedConsent = Consent::query()
                ->lockForUpdate()
                ->findOrFail($consent->id);

            Consent::runWithinManagedWorkflow(function () use ($lockedConsent): void {
                $lockedConsent->status = Consent::STATUS_EXPIRED;
                $lockedConsent->save();
            }, array_filter([
                'actor_id' => $this->resolveActorId($actorId, $lockedConsent),
                'reason' => $reason,
                'trigger' => $trigger,
            ], static fn (mixed $value): bool => $value !== null));

            return $lockedConsent->fresh();
        }, 3);
    }

    protected function resolveActorId(?int $actorId, Consent $consent): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : $consent->signed_by);
    }
}
