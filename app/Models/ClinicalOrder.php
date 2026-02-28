<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ClinicalOrder extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'patient_id',
        'visit_episode_id',
        'clinical_note_id',
        'branch_id',
        'ordered_by',
        'order_code',
        'order_type',
        'status',
        'requested_at',
        'completed_at',
        'payload',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'payload' => 'array',
            'notes' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $order): void {
            $order->status = strtolower(trim((string) ($order->status ?: self::STATUS_PENDING)));

            if ($order->exists && $order->isDirty('status')) {
                $fromStatus = strtolower(trim((string) ($order->getOriginal('status') ?: self::STATUS_PENDING)));
                $toStatus = strtolower(trim((string) ($order->status ?: self::STATUS_PENDING)));

                if (! self::canTransition($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => 'CLINICAL_ORDER_STATE_INVALID: Không thể chuyển trạng thái chỉ định.',
                    ]);
                }
            }

            if (blank($order->order_code)) {
                $order->order_code = self::generateOrderCode();
            }

            if (blank($order->patient_id)) {
                $order->patient_id = self::inferPatientId($order);
            }

            if (blank($order->visit_episode_id)) {
                $order->visit_episode_id = self::inferVisitEpisodeId($order);
            }

            if (blank($order->branch_id)) {
                $order->branch_id = self::inferBranchId($order);
            }

            if (blank($order->ordered_by) && auth()->check()) {
                $order->ordered_by = (int) auth()->id();
            }

            if (blank($order->requested_at)) {
                $order->requested_at = now();
            }

            if ($order->status === self::STATUS_COMPLETED && blank($order->completed_at)) {
                $order->completed_at = now();
            }
        });
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

    public function clinicalNote(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ClinicalResult::class);
    }

    public function markInProgress(): void
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->save();
    }

    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->save();
    }

    public function cancel(?string $reason = null): void
    {
        $this->status = self::STATUS_CANCELLED;
        if (filled($reason)) {
            $this->notes = trim((string) (($this->notes ? $this->notes."\n" : '').$reason));
        }
        $this->save();
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function generateOrderCode(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd').'-';

        return Cache::lock("clinical_order:{$prefix}", 5)->block(5, function () use ($prefix): string {
            $latestCode = self::withTrashed()
                ->where('order_code', 'like', $prefix.'%')
                ->orderByDesc('order_code')
                ->value('order_code');

            $sequence = 1;

            if (is_string($latestCode) && preg_match('/(\d{4})$/', $latestCode, $matches) === 1) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    protected static function inferPatientId(self $order): ?int
    {
        if ($order->clinical_note_id) {
            $notePatientId = ClinicalNote::query()
                ->whereKey((int) $order->clinical_note_id)
                ->value('patient_id');

            if ($notePatientId !== null) {
                return (int) $notePatientId;
            }
        }

        if (! $order->visit_episode_id) {
            return null;
        }

        $episodePatientId = VisitEpisode::query()
            ->whereKey((int) $order->visit_episode_id)
            ->value('patient_id');

        return $episodePatientId !== null ? (int) $episodePatientId : null;
    }

    protected static function inferVisitEpisodeId(self $order): ?int
    {
        if (! $order->clinical_note_id) {
            return null;
        }

        $episodeId = ClinicalNote::query()
            ->whereKey((int) $order->clinical_note_id)
            ->value('visit_episode_id');

        return $episodeId !== null ? (int) $episodeId : null;
    }

    protected static function inferBranchId(self $order): ?int
    {
        if ($order->clinical_note_id) {
            $noteBranchId = ClinicalNote::query()
                ->whereKey((int) $order->clinical_note_id)
                ->value('branch_id');

            if ($noteBranchId !== null) {
                return (int) $noteBranchId;
            }
        }

        if ($order->visit_episode_id) {
            $episodeBranchId = VisitEpisode::query()
                ->whereKey((int) $order->visit_episode_id)
                ->value('branch_id');

            if ($episodeBranchId !== null) {
                return (int) $episodeBranchId;
            }
        }

        if (! $order->patient_id) {
            return null;
        }

        $patientBranchId = Patient::query()
            ->whereKey((int) $order->patient_id)
            ->value('first_branch_id');

        return $patientBranchId !== null ? (int) $patientBranchId : null;
    }
}
