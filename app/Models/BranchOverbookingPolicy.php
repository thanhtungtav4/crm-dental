<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOverbookingPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'is_enabled',
        'max_parallel_per_doctor',
        'require_override_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'require_override_reason' => 'boolean',
            'max_parallel_per_doctor' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function resolveForBranch(?int $branchId): self
    {
        if (! $branchId) {
            return new self([
                'is_enabled' => false,
                'max_parallel_per_doctor' => 1,
                'require_override_reason' => true,
            ]);
        }

        return self::query()->firstOrCreate(
            ['branch_id' => $branchId],
            [
                'is_enabled' => false,
                'max_parallel_per_doctor' => 1,
                'require_override_reason' => true,
            ],
        );
    }
}
