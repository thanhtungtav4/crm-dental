<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorBranchAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'is_active',
        'is_primary',
        'assigned_from',
        'assigned_until',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
            'assigned_from' => 'date',
            'assigned_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActiveAt(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $referenceDate = ($at ?? now())->toDateString();

        return $query
            ->active()
            ->where(function (Builder $innerQuery) use ($referenceDate): void {
                $innerQuery
                    ->whereNull('assigned_from')
                    ->orWhereDate('assigned_from', '<=', $referenceDate);
            })
            ->where(function (Builder $innerQuery) use ($referenceDate): void {
                $innerQuery
                    ->whereNull('assigned_until')
                    ->orWhereDate('assigned_until', '>=', $referenceDate);
            });
    }
}
