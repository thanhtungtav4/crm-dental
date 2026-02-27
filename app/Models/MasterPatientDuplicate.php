<?php

namespace App\Models;

use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterPatientDuplicate extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'patient_id',
        'branch_id',
        'identity_type',
        'identity_hash',
        'identity_value',
        'matched_patient_ids',
        'matched_branch_ids',
        'confidence_score',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'matched_patient_ids' => 'array',
            'matched_branch_ids' => 'array',
            'confidence_score' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function markResolved(?int $reviewedBy = null, ?string $note = null): void
    {
        ActionGate::authorize(
            ActionPermission::MPI_DEDUPE_REVIEW,
            'Bạn không có quyền xử lý queue trùng bệnh nhân liên chi nhánh.',
        );

        $this->status = self::STATUS_RESOLVED;
        $this->reviewed_by = $reviewedBy ?? auth()->id();
        $this->reviewed_at = now();
        $this->review_note = $note;
        $this->save();
    }

    public function markIgnored(?int $reviewedBy = null, ?string $note = null): void
    {
        ActionGate::authorize(
            ActionPermission::MPI_DEDUPE_REVIEW,
            'Bạn không có quyền xử lý queue trùng bệnh nhân liên chi nhánh.',
        );

        $this->status = self::STATUS_IGNORED;
        $this->reviewed_by = $reviewedBy ?? auth()->id();
        $this->reviewed_at = now();
        $this->review_note = $note;
        $this->save();
    }
}
