<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientLoyalty;
use App\Models\PatientLoyaltyTransaction;
use App\Models\Payment;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PatientLoyaltyService
{
    /**
     * @return array{
     *     revenue_earned:int,
     *     revenue_skipped:int,
     *     referral_linked:int,
     *     referral_rewarded:int,
     *     referral_skipped:int
     * }
     */
    public function runProgram(Carbon $asOf, bool $persist = true, ?int $actorId = null): array
    {
        $revenue = $this->syncRevenuePoints($asOf, $persist, $actorId);
        $referral = $this->processReferralBonuses($asOf, $persist, $actorId);

        return [
            'revenue_earned' => $revenue['earned'],
            'revenue_skipped' => $revenue['skipped'],
            'referral_linked' => $referral['linked'],
            'referral_rewarded' => $referral['rewarded'],
            'referral_skipped' => $referral['skipped'],
        ];
    }

    /**
     * @return array{earned:int,skipped:int}
     */
    public function syncRevenuePoints(Carbon $asOf, bool $persist = true, ?int $actorId = null): array
    {
        $pointsPerTenThousandVnd = max(0, ClinicRuntimeSettings::loyaltyPointsPerTenThousandVnd());
        $earned = 0;
        $skipped = 0;

        Payment::query()
            ->with(['invoice:id,patient_id'])
            ->where('direction', 'receipt')
            ->where('amount', '>', 0)
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $asOf)
            ->orderBy('id')
            ->chunkById(200, function ($payments) use (
                &$earned,
                &$skipped,
                $persist,
                $pointsPerTenThousandVnd,
                $actorId,
            ): void {
                foreach ($payments as $payment) {
                    $patientId = (int) ($payment->invoice?->patient_id ?? 0);
                    if ($patientId <= 0) {
                        continue;
                    }

                    if ($this->hasExistingEvent(
                        patientId: $patientId,
                        eventType: PatientLoyaltyTransaction::EVENT_REVENUE_EARN,
                        sourceType: Payment::class,
                        sourceId: $payment->id,
                    )) {
                        $skipped++;

                        continue;
                    }

                    $account = $this->ensureAccountByPatientId($patientId, $persist);
                    $amount = max(0, round((float) $payment->amount, 2));
                    $points = (int) floor(($amount / 10000) * $pointsPerTenThousandVnd);

                    if (! $persist) {
                        $earned++;

                        continue;
                    }

                    DB::transaction(function () use (
                        $account,
                        $payment,
                        $points,
                        $amount,
                        $actorId,
                    ): void {
                        PatientLoyaltyTransaction::query()->create([
                            'patient_loyalty_id' => $account->id,
                            'patient_id' => $account->patient_id,
                            'event_type' => PatientLoyaltyTransaction::EVENT_REVENUE_EARN,
                            'points_delta' => $points,
                            'amount' => $amount,
                            'source_type' => Payment::class,
                            'source_id' => $payment->id,
                            'occurred_at' => $payment->paid_at ?? now(),
                            'created_by' => $actorId,
                            'metadata' => [
                                'invoice_id' => $payment->invoice_id,
                                'payment_id' => $payment->id,
                            ],
                        ]);

                        $nextLifetimeRevenue = round((float) $account->lifetime_revenue + $amount, 2);
                        $nextTier = $this->resolveTierByRevenue($nextLifetimeRevenue);

                        $account->update([
                            'points_balance' => (int) $account->points_balance + $points,
                            'lifetime_points_earned' => (int) $account->lifetime_points_earned + $points,
                            'lifetime_revenue' => $nextLifetimeRevenue,
                            'tier' => $nextTier,
                            'last_activity_at' => $payment->paid_at ?? now(),
                        ]);
                    });

                    $earned++;
                }
            });

        return [
            'earned' => $earned,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{linked:int,rewarded:int,skipped:int}
     */
    public function processReferralBonuses(Carbon $asOf, bool $persist = true, ?int $actorId = null): array
    {
        $linked = 0;
        $rewarded = 0;
        $skipped = 0;

        PatientLoyalty::query()
            ->with(['patient.customer'])
            ->whereNull('referral_code_used')
            ->orderBy('id')
            ->chunkById(200, function ($accounts) use ($persist): void {
                foreach ($accounts as $account) {
                    $code = $this->extractReferralCodeFromPatient($account->patient);
                    if ($code === null || ! $persist) {
                        continue;
                    }

                    $account->update([
                        'referral_code_used' => $code,
                    ]);
                }
            });

        PatientLoyalty::query()
            ->whereNull('referred_by_patient_id')
            ->whereNotNull('referral_code_used')
            ->orderBy('id')
            ->chunkById(200, function ($accounts) use (&$linked, &$skipped, $persist, $asOf): void {
                foreach ($accounts as $account) {
                    $usedCode = strtoupper(trim((string) $account->referral_code_used));
                    if ($usedCode === '') {
                        continue;
                    }

                    $referrer = PatientLoyalty::query()
                        ->where('referral_code', $usedCode)
                        ->first();

                    if (! $referrer || $referrer->patient_id === $account->patient_id) {
                        $skipped++;

                        continue;
                    }

                    if (! $persist) {
                        $linked++;

                        continue;
                    }

                    $account->update([
                        'referred_by_patient_id' => $referrer->patient_id,
                        'referred_at' => $asOf,
                    ]);

                    $linked++;
                }
            });

        $referrerBonus = max(0, ClinicRuntimeSettings::loyaltyReferralBonusReferrerPoints());
        $refereeBonus = max(0, ClinicRuntimeSettings::loyaltyReferralBonusRefereePoints());

        PatientLoyalty::query()
            ->whereNotNull('referred_by_patient_id')
            ->whereNotNull('referred_at')
            ->where('referred_at', '<=', $asOf)
            ->orderBy('id')
            ->chunkById(200, function ($accounts) use (
                &$rewarded,
                &$skipped,
                $persist,
                $actorId,
                $referrerBonus,
                $refereeBonus,
            ): void {
                foreach ($accounts as $account) {
                    if ($this->hasExistingEvent(
                        patientId: $account->patient_id,
                        eventType: PatientLoyaltyTransaction::EVENT_REFERRAL_BONUS_REFEREE,
                        sourceType: Patient::class,
                        sourceId: $account->patient_id,
                    )) {
                        $skipped++;

                        continue;
                    }

                    $referrerAccount = $this->ensureAccountByPatientId((int) $account->referred_by_patient_id, $persist);
                    if (! $persist) {
                        $rewarded++;

                        continue;
                    }

                    DB::transaction(function () use (
                        $account,
                        $referrerAccount,
                        $referrerBonus,
                        $refereeBonus,
                        $actorId,
                    ): void {
                        PatientLoyaltyTransaction::query()->create([
                            'patient_loyalty_id' => $referrerAccount->id,
                            'patient_id' => $referrerAccount->patient_id,
                            'event_type' => PatientLoyaltyTransaction::EVENT_REFERRAL_BONUS_REFERRER,
                            'points_delta' => $referrerBonus,
                            'amount' => null,
                            'source_type' => Patient::class,
                            'source_id' => $account->patient_id,
                            'occurred_at' => now(),
                            'created_by' => $actorId,
                            'metadata' => [
                                'referred_patient_id' => $account->patient_id,
                            ],
                        ]);

                        PatientLoyaltyTransaction::query()->create([
                            'patient_loyalty_id' => $account->id,
                            'patient_id' => $account->patient_id,
                            'event_type' => PatientLoyaltyTransaction::EVENT_REFERRAL_BONUS_REFEREE,
                            'points_delta' => $refereeBonus,
                            'amount' => null,
                            'source_type' => Patient::class,
                            'source_id' => $account->patient_id,
                            'occurred_at' => now(),
                            'created_by' => $actorId,
                            'metadata' => [
                                'referrer_patient_id' => $account->referred_by_patient_id,
                            ],
                        ]);

                        $referrerAccount->update([
                            'points_balance' => (int) $referrerAccount->points_balance + $referrerBonus,
                            'lifetime_points_earned' => (int) $referrerAccount->lifetime_points_earned + $referrerBonus,
                            'last_activity_at' => now(),
                        ]);

                        $account->update([
                            'points_balance' => (int) $account->points_balance + $refereeBonus,
                            'lifetime_points_earned' => (int) $account->lifetime_points_earned + $refereeBonus,
                            'last_activity_at' => now(),
                        ]);
                    });

                    $rewarded++;
                }
            });

        return [
            'linked' => $linked,
            'rewarded' => $rewarded,
            'skipped' => $skipped,
        ];
    }

    public function applyReactivationBonus(Patient $patient, Note $ticket, bool $persist = true, ?int $actorId = null): bool
    {
        if ($this->hasExistingEvent(
            patientId: $patient->id,
            eventType: PatientLoyaltyTransaction::EVENT_REACTIVATION_BONUS,
            sourceType: Note::class,
            sourceId: $ticket->id,
        )) {
            return false;
        }

        $bonusPoints = max(0, ClinicRuntimeSettings::loyaltyReactivationBonusPoints());
        if ($bonusPoints <= 0) {
            return false;
        }

        if (! $persist) {
            return true;
        }

        $account = $this->ensureAccount($patient);

        DB::transaction(function () use ($account, $ticket, $bonusPoints, $actorId): void {
            PatientLoyaltyTransaction::query()->create([
                'patient_loyalty_id' => $account->id,
                'patient_id' => $account->patient_id,
                'event_type' => PatientLoyaltyTransaction::EVENT_REACTIVATION_BONUS,
                'points_delta' => $bonusPoints,
                'amount' => null,
                'source_type' => Note::class,
                'source_id' => $ticket->id,
                'occurred_at' => now(),
                'created_by' => $actorId,
                'metadata' => [
                    'ticket_id' => $ticket->id,
                    'care_type' => $ticket->care_type,
                ],
            ]);

            $account->update([
                'points_balance' => (int) $account->points_balance + $bonusPoints,
                'lifetime_points_earned' => (int) $account->lifetime_points_earned + $bonusPoints,
                'last_reactivation_at' => now(),
                'last_activity_at' => now(),
            ]);
        });

        return true;
    }

    public function ensureAccount(Patient $patient, bool $persist = true): PatientLoyalty
    {
        $existing = PatientLoyalty::query()
            ->where('patient_id', $patient->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $attributes = [
            'patient_id' => $patient->id,
            'referral_code' => $this->generateReferralCode($patient),
            'referral_code_used' => $this->extractReferralCodeFromPatient($patient),
            'referred_by_patient_id' => null,
            'referred_at' => null,
            'tier' => PatientLoyalty::TIER_BRONZE,
            'points_balance' => 0,
            'lifetime_points_earned' => 0,
            'lifetime_points_redeemed' => 0,
            'lifetime_revenue' => 0,
            'last_reactivation_at' => null,
            'last_activity_at' => null,
            'metadata' => null,
        ];

        if (! $persist) {
            return new PatientLoyalty($attributes);
        }

        return PatientLoyalty::query()->create($attributes);
    }

    protected function ensureAccountByPatientId(int $patientId, bool $persist = true): PatientLoyalty
    {
        $patient = Patient::query()
            ->whereKey($patientId)
            ->firstOrFail();

        return $this->ensureAccount($patient, $persist);
    }

    protected function hasExistingEvent(int $patientId, string $eventType, string $sourceType, int $sourceId): bool
    {
        return PatientLoyaltyTransaction::query()
            ->where('patient_id', $patientId)
            ->where('event_type', $eventType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }

    protected function resolveTierByRevenue(float $lifetimeRevenue): string
    {
        if ($lifetimeRevenue >= ClinicRuntimeSettings::loyaltyTierPlatinumRevenueThreshold()) {
            return PatientLoyalty::TIER_PLATINUM;
        }

        if ($lifetimeRevenue >= ClinicRuntimeSettings::loyaltyTierGoldRevenueThreshold()) {
            return PatientLoyalty::TIER_GOLD;
        }

        if ($lifetimeRevenue >= ClinicRuntimeSettings::loyaltyTierSilverRevenueThreshold()) {
            return PatientLoyalty::TIER_SILVER;
        }

        return PatientLoyalty::TIER_BRONZE;
    }

    protected function generateReferralCode(Patient $patient): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $patient->patient_code));
        $base = $base !== '' ? substr($base, -8) : strtoupper(dechex((int) $patient->id));

        $attempt = 0;
        do {
            $suffix = $attempt === 0
                ? strtoupper(str_pad((string) $base, 8, 'X', STR_PAD_LEFT))
                : strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $referralCode = 'REF'.$suffix;
            $attempt++;
        } while (
            PatientLoyalty::query()
                ->where('referral_code', $referralCode)
                ->exists()
        );

        return $referralCode;
    }

    protected function extractReferralCodeFromPatient(?Patient $patient): ?string
    {
        if (! $patient) {
            return null;
        }

        $patient->loadMissing('customer');
        $customer = $patient->customer;
        if (! $customer || strtolower((string) $customer->source) !== 'referral') {
            return null;
        }

        $haystack = trim((string) ($customer->notes ?? ''));
        if ($haystack === '') {
            return null;
        }

        if (preg_match('/ref(?:erral)?\s*[:#-]?\s*([a-z0-9]{6,32})/i', $haystack, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\bREF[a-z0-9]{6,32}\b/i', $haystack, $matches) === 1) {
            return strtoupper($matches[0]);
        }

        return null;
    }
}
