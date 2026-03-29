<?php

namespace App\Models;

use App\Support\BranchAccess;
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

    public const STATUS_OPEN = 'open';

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

        return 'Khách Zalo '.$suffix;
    }

    public function latestPreview(): string
    {
        $preview = trim((string) $this->latest_message_preview);

        return $preview !== '' ? $preview : 'Chưa có tin nhắn';
    }
}
