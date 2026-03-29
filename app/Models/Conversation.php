<?php

namespace App\Models;

use App\Support\BranchAccess;
use App\Support\ConversationProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationFactory> */
    use HasFactory;

    public const PROVIDER_ZALO = 'zalo';

    public const PROVIDER_FACEBOOK = 'facebook';

    public const STATUS_OPEN = 'open';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const HANDOFF_STATUS_NEW = 'new';

    public const HANDOFF_STATUS_CONSULTING = 'consulting';

    public const HANDOFF_STATUS_QUOTED = 'quoted';

    public const HANDOFF_STATUS_WAITING_CUSTOMER = 'waiting_customer';

    public const HANDOFF_STATUS_FOLLOW_UP = 'follow_up';

    protected $fillable = [
        'provider',
        'channel_key',
        'external_conversation_key',
        'external_user_id',
        'external_display_name',
        'branch_id',
        'customer_id',
        'assigned_to',
        'status',
        'unread_count',
        'latest_message_preview',
        'last_message_at',
        'last_inbound_at',
        'last_outbound_at',
        'handoff_priority',
        'handoff_status',
        'handoff_summary',
        'handoff_next_action_at',
        'handoff_updated_by',
        'handoff_updated_at',
        'handoff_version',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'customer_id' => 'integer',
            'assigned_to' => 'integer',
            'unread_count' => 'integer',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
            'handoff_next_action_at' => 'datetime',
            'handoff_updated_by' => 'integer',
            'handoff_updated_at' => 'datetime',
            'handoff_version' => 'integer',
        ];
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($user, false);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function handoffEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handoff_updated_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)
            ->orderBy('message_at')
            ->orderBy('id');
    }

    public function displayName(): string
    {
        $displayName = trim((string) $this->external_display_name);

        if ($displayName !== '') {
            return $displayName;
        }

        $normalizedExternalId = preg_replace('/[^A-Za-z0-9]/', '', (string) $this->external_user_id) ?: '';
        $suffix = Str::upper(Str::limit($normalizedExternalId !== '' ? $normalizedExternalId : (string) $this->getKey(), 6, ''));
        $provider = $this->providerEnum();

        return $provider instanceof ConversationProvider
            ? $provider->fallbackCustomerLabel($suffix)
            : 'Khách '.$suffix;
    }

    public function latestPreview(): string
    {
        $preview = trim((string) $this->latest_message_preview);

        return $preview !== '' ? $preview : 'Chưa có tin nhắn';
    }

    public function providerEnum(): ?ConversationProvider
    {
        return ConversationProvider::tryFromNullable($this->provider);
    }

    public function providerLabel(): string
    {
        return $this->providerEnum()?->label() ?? strtoupper((string) $this->provider);
    }

    /**
     * @return array<string, string>
     */
    public static function handoffPriorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => 'Theo dõi',
            self::PRIORITY_NORMAL => 'Bình thường',
            self::PRIORITY_HIGH => 'Ưu tiên cao',
            self::PRIORITY_URGENT => 'Khẩn',
        ];
    }

    public function handoffPriorityLabel(): string
    {
        return static::handoffPriorityOptions()[$this->handoffPriorityValue()] ?? 'Bình thường';
    }

    public function handoffPriorityValue(): string
    {
        $priority = trim((string) $this->handoff_priority);

        return array_key_exists($priority, static::handoffPriorityOptions())
            ? $priority
            : static::PRIORITY_NORMAL;
    }

    /**
     * @return array<string, string>
     */
    public static function handoffStatusOptions(): array
    {
        return [
            self::HANDOFF_STATUS_NEW => 'Mới vào',
            self::HANDOFF_STATUS_CONSULTING => 'Đang tư vấn',
            self::HANDOFF_STATUS_QUOTED => 'Đã báo giá',
            self::HANDOFF_STATUS_WAITING_CUSTOMER => 'Chờ khách phản hồi',
            self::HANDOFF_STATUS_FOLLOW_UP => 'Cần follow-up',
        ];
    }

    public function handoffStatusLabel(): string
    {
        return static::handoffStatusOptions()[$this->handoffStatusValue()] ?? 'Mới vào';
    }

    public function handoffStatusValue(): string
    {
        $status = trim((string) $this->handoff_status);

        return array_key_exists($status, static::handoffStatusOptions())
            ? $status
            : static::HANDOFF_STATUS_NEW;
    }

    public function handoffSummaryPreview(int $limit = 96): ?string
    {
        $summary = trim((string) $this->handoff_summary);

        if ($summary === '') {
            return null;
        }

        return Str::limit($summary, $limit);
    }

    public function handoffNextActionLabel(string $format = 'd/m H:i'): ?string
    {
        return $this->handoff_next_action_at?->format($format);
    }
}
