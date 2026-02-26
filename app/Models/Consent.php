<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Consent extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'patient_id',
        'service_id',
        'plan_item_id',
        'consent_type',
        'consent_version',
        'status',
        'signed_by',
        'signed_at',
        'expires_at',
        'revoked_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $consent): void {
            $consent->status = strtolower(trim((string) $consent->status));

            if ($consent->status === self::STATUS_SIGNED) {
                if (! $consent->signed_by) {
                    throw ValidationException::withMessages([
                        'signed_by' => 'Consent đã ký cần người ký xác nhận.',
                    ]);
                }

                $consent->signed_at = $consent->signed_at ?? now();
            }

            if ($consent->status === self::STATUS_REVOKED) {
                $consent->revoked_at = $consent->revoked_at ?? now();
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(PlanItem::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function scopeSigned($query)
    {
        return $query->where('status', self::STATUS_SIGNED);
    }

    public function scopeValidAt($query, Carbon $moment)
    {
        return $query
            ->where('status', self::STATUS_SIGNED)
            ->where(function ($innerQuery) use ($moment): void {
                $innerQuery->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $moment);
            });
    }

    public function isValidAt(Carbon $moment): bool
    {
        if ($this->status !== self::STATUS_SIGNED) {
            return false;
        }

        if ($this->expires_at === null) {
            return true;
        }

        return Carbon::parse($this->expires_at)->gte($moment);
    }
}
