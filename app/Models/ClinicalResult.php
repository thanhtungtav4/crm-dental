<?php

namespace App\Models;

use App\Casts\NullableEncrypted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalResult extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PRELIMINARY = 'preliminary';

    public const STATUS_FINAL = 'final';

    public const STATUS_AMENDED = 'amended';

    protected const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PRELIMINARY, self::STATUS_FINAL],
        self::STATUS_PRELIMINARY => [self::STATUS_FINAL, self::STATUS_AMENDED],
        self::STATUS_FINAL => [self::STATUS_AMENDED],
        self::STATUS_AMENDED => [],
    ];

    protected $fillable = [
        'clinical_order_id',
        'patient_id',
        'visit_episode_id',
        'branch_id',
        'verified_by',
        'result_code',
        'status',
        'resulted_at',
        'verified_at',
        'payload',
        'interpretation',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'resulted_at' => 'datetime',
            'verified_at' => 'datetime',
            'payload' => 'array',
            'interpretation' => NullableEncrypted::class,
            'notes' => NullableEncrypted::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $result): void {
            $result->status = strtolower(trim((string) ($result->status ?: self::STATUS_DRAFT)));

            if ($result->exists && $result->isDirty('status')) {
                $fromStatus = strtolower(trim((string) ($result->getOriginal('status') ?: self::STATUS_DRAFT)));
                $toStatus = strtolower(trim((string) ($result->status ?: self::STATUS_DRAFT)));

                if (! self::canTransition($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => 'CLINICAL_RESULT_STATE_INVALID: Không thể chuyển trạng thái kết quả chỉ định.',
                    ]);
                }
            }

            if (blank($result->result_code)) {
                $result->result_code = self::generateResultCode();
            }

            if (blank($result->patient_id)) {
                $result->patient_id = self::inferPatientId($result);
            }

            if (blank($result->visit_episode_id)) {
                $result->visit_episode_id = self::inferVisitEpisodeId($result);
            }

            if (blank($result->branch_id)) {
                $result->branch_id = self::inferBranchId($result);
            }

            if (in_array($result->status, [self::STATUS_PRELIMINARY, self::STATUS_FINAL, self::STATUS_AMENDED], true)
                && blank($result->resulted_at)) {
                $result->resulted_at = now();
            }

            if ($result->status === self::STATUS_FINAL && blank($result->verified_by) && auth()->check()) {
                $result->verified_by = (int) auth()->id();
            }

            if (in_array($result->status, [self::STATUS_FINAL, self::STATUS_AMENDED], true) && blank($result->verified_at)) {
                $result->verified_at = now();
            }
        });

        static::saved(function (self $result): void {
            if (! in_array($result->status, [self::STATUS_FINAL, self::STATUS_AMENDED], true)) {
                return;
            }

            $order = $result->clinicalOrder;

            if (! $order || $order->status === ClinicalOrder::STATUS_CANCELLED || $order->status === ClinicalOrder::STATUS_COMPLETED) {
                return;
            }

            $order->markCompleted();
        });
    }

    public function clinicalOrder(): BelongsTo
    {
        return $this->belongsTo(ClinicalOrder::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'visit_episode_id');
    }

    public function visitEpisode(): BelongsTo
    {
        return $this->belongsTo(VisitEpisode::class, 'visit_episode_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function markPreliminary(?array $payload = null, ?string $interpretation = null, ?string $notes = null): void
    {
        if ($payload !== null) {
            $this->payload = $payload;
        }

        if ($interpretation !== null) {
            $this->interpretation = $interpretation;
        }

        if ($notes !== null) {
            $this->notes = $notes;
        }

        $this->status = self::STATUS_PRELIMINARY;
        $this->save();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function finalize(?int $verifiedBy = null, ?array $payload = null, ?string $interpretation = null, ?string $notes = null): void
    {
        DB::transaction(function () use ($verifiedBy, $payload, $interpretation, $notes): void {
            if ($payload !== null) {
                $this->payload = $payload;
            }

            if ($interpretation !== null) {
                $this->interpretation = $interpretation;
            }

            if ($notes !== null) {
                $this->notes = $notes;
            }

            if ($verifiedBy !== null) {
                $this->verified_by = $verifiedBy;
            }

            $this->status = self::STATUS_FINAL;
            $this->save();
        }, 3);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function amend(?array $payload = null, ?string $interpretation = null, ?string $notes = null): void
    {
        if ($payload !== null) {
            $this->payload = $payload;
        }

        if ($interpretation !== null) {
            $this->interpretation = $interpretation;
        }

        if ($notes !== null) {
            $this->notes = $notes;
        }

        $this->status = self::STATUS_AMENDED;
        $this->save();
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function generateResultCode(): string
    {
        $prefix = 'RES-'.now()->format('Ymd').'-';

        return Cache::lock("clinical_result:{$prefix}", 5)->block(5, function () use ($prefix): string {
            $latestCode = self::withTrashed()
                ->where('result_code', 'like', $prefix.'%')
                ->orderByDesc('result_code')
                ->value('result_code');

            $sequence = 1;

            if (is_string($latestCode) && preg_match('/(\d{4})$/', $latestCode, $matches) === 1) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    protected static function inferPatientId(self $result): ?int
    {
        if (! $result->clinical_order_id) {
            return null;
        }

        $patientId = ClinicalOrder::query()
            ->whereKey((int) $result->clinical_order_id)
            ->value('patient_id');

        return $patientId !== null ? (int) $patientId : null;
    }

    protected static function inferVisitEpisodeId(self $result): ?int
    {
        if (! $result->clinical_order_id) {
            return null;
        }

        $episodeId = ClinicalOrder::query()
            ->whereKey((int) $result->clinical_order_id)
            ->value('visit_episode_id');

        return $episodeId !== null ? (int) $episodeId : null;
    }

    protected static function inferBranchId(self $result): ?int
    {
        if (! $result->clinical_order_id) {
            return null;
        }

        $branchId = ClinicalOrder::query()
            ->whereKey((int) $result->clinical_order_id)
            ->value('branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }
}
