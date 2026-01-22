<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'reminder_type',
        'due_date',
        'sent_at',
        'delivery_method',
        'status',
        'message',
    ];

    protected $casts = [
        'due_date' => 'date',
        'sent_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get reminder type label in Vietnamese
     */
    public function getTypeLabel(): string
    {
        return match($this->reminder_type) {
            'first' => 'Nhắc nhở lần 1',
            'second' => 'Nhắc nhở lần 2',
            'final' => 'Thông báo cuối',
            default => 'Không xác định',
        };
    }

    /**
     * Get status label in Vietnamese
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Chờ gửi',
            'sent' => 'Đã gửi',
            'failed' => 'Thất bại',
            default => 'Không xác định',
        };
    }

    /**
     * Get delivery method label in Vietnamese
     */
    public function getMethodLabel(): string
    {
        return match($this->delivery_method) {
            'email' => 'Email',
            'sms' => 'SMS',
            'both' => 'Email & SMS',
            'notification' => 'Thông báo hệ thống',
            default => 'Không xác định',
        };
    }

    /**
     * Mark reminder as sent
     */
    public function markAsSent(): void
    {
        $this->status = 'sent';
        $this->sent_at = now();
        $this->save();
    }

    /**
     * Mark reminder as failed
     */
    public function markAsFailed(): void
    {
        $this->status = 'failed';
        $this->save();
    }

    // ==================== SCOPES ====================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('reminder_type', $type);
    }
}
