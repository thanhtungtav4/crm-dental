<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterPatientIdentity extends Model
{
    use HasFactory;

    public const TYPE_PHONE = 'phone';

    public const TYPE_EMAIL = 'email';

    public const TYPE_CCCD = 'cccd';

    protected $fillable = [
        'patient_id',
        'branch_id',
        'identity_type',
        'identity_hash',
        'identity_value',
        'is_primary',
        'confidence_score',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'confidence_score' => 'decimal:2',
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
}
