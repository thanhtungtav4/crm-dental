<?php

namespace App\Models;

use App\Casts\NullableEncryptedArray;
use App\Casts\NullableEncryptedWithPlaintextFallback;
use App\Services\ZnsPayloadSanitizer;
use App\Support\PatientIdentityNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZnsCampaignDelivery extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'zns_campaign_id',
        'patient_id',
        'customer_id',
        'branch_id',
        'phone',
        'normalized_phone',
        'phone_search_hash',
        'idempotency_key',
        'status',
        'processing_token',
        'locked_at',
        'attempt_count',
        'provider_message_id',
        'provider_status_code',
        'provider_response',
        'error_message',
        'sent_at',
        'next_retry_at',
        'payload',
        'template_key_snapshot',
        'template_id_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zns_campaign_id' => 'integer',
            'patient_id' => 'integer',
            'customer_id' => 'integer',
            'branch_id' => 'integer',
            'attempt_count' => 'integer',
            'sent_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'locked_at' => 'datetime',
            'phone' => NullableEncryptedWithPlaintextFallback::class,
            'normalized_phone' => NullableEncryptedWithPlaintextFallback::class,
            'payload' => NullableEncryptedArray::class,
            'provider_response' => NullableEncryptedArray::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $delivery): void {
            $delivery->phone_search_hash = self::phoneSearchHash(
                $delivery->normalized_phone ?: $delivery->phone,
            );
        });
    }

    public static function phoneSearchHash(?string $phone): ?string
    {
        $normalized = PatientIdentityNormalizer::normalizePhone($phone);

        return $normalized === null
            ? null
            : hash('sha256', 'zns-phone|'.$normalized);
    }

    public function maskedPhone(): ?string
    {
        return app(ZnsPayloadSanitizer::class)->maskPhone($this->normalized_phone ?: $this->phone);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ZnsCampaign::class, 'zns_campaign_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
