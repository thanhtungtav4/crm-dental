<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopupAnnouncementDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SEEN = 'seen';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'popup_announcement_id',
        'user_id',
        'branch_id',
        'status',
        'delivered_at',
        'seen_at',
        'acknowledged_at',
        'dismissed_at',
        'expired_at',
        'display_count',
        'last_displayed_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'popup_announcement_id' => 'integer',
            'user_id' => 'integer',
            'branch_id' => 'integer',
            'delivered_at' => 'datetime',
            'seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'expired_at' => 'datetime',
            'display_count' => 'integer',
            'last_displayed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PopupAnnouncement::class, 'popup_announcement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUndone(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [self::STATUS_DISMISSED, self::STATUS_ACKNOWLEDGED, self::STATUS_EXPIRED])
            ->whereNull('dismissed_at')
            ->whereNull('acknowledged_at')
            ->whereNull('expired_at');
    }

    public function markSeen(): void
    {
        $now = now();

        $this->forceFill([
            'status' => self::STATUS_SEEN,
            'seen_at' => $this->seen_at ?? $now,
            'display_count' => (int) $this->display_count + 1,
            'last_displayed_at' => $now,
        ])->save();
    }

    public function markAcknowledged(): void
    {
        $now = now();

        $this->forceFill([
            'status' => self::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => $now,
            'dismissed_at' => $this->dismissed_at,
        ])->save();
    }

    public function markDismissed(): void
    {
        $now = now();

        $this->forceFill([
            'status' => self::STATUS_DISMISSED,
            'dismissed_at' => $now,
        ])->save();
    }
}
