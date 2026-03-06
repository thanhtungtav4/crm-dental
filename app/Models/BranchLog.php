<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class BranchLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'from_branch_id',
        'to_branch_id',
        'moved_by',
        'note',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ValidationException::withMessages([
                'branch_log' => 'Branch log là immutable, không cho phép cập nhật.',
            ]);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'branch_log' => 'Branch log là immutable, không cho phép xóa.',
            ]);
        });
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Admin')) {
            return $query;
        }

        if (! $user->can('ViewAny:BranchLog')) {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = BranchAccess::accessibleBranchIds($user);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($branchIds): void {
            $query->whereIn('from_branch_id', $branchIds)
                ->orWhereIn('to_branch_id', $branchIds)
                ->orWhereHas('patient', fn (Builder $patientQuery): Builder => $patientQuery->whereIn('first_branch_id', $branchIds));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if (! $user->can('View:BranchLog')) {
            return false;
        }

        $branchIds = BranchAccess::accessibleBranchIds($user);

        if ($branchIds === []) {
            return false;
        }

        return in_array((int) $this->from_branch_id, $branchIds, true)
            || in_array((int) $this->to_branch_id, $branchIds, true)
            || in_array((int) ($this->patient?->first_branch_id ?? 0), $branchIds, true);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
