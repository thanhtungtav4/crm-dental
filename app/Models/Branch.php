<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'active',
        'manager_id',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'first_branch_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function branchLogs()
    {
        return $this->hasMany(BranchLog::class, 'to_branch_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function doctorBranchAssignments()
    {
        return $this->hasMany(DoctorBranchAssignment::class);
    }

    public function transferRequestsFrom()
    {
        return $this->hasMany(BranchTransferRequest::class, 'from_branch_id');
    }

    public function transferRequestsTo()
    {
        return $this->hasMany(BranchTransferRequest::class, 'to_branch_id');
    }

    public function overbookingPolicy()
    {
        return $this->hasOne(BranchOverbookingPolicy::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Admin')) {
            return $query;
        }

        if (! $user->can('ViewAny:Branch')) {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = BranchAccess::accessibleBranchIds($user, false);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $branchIds);
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if (! $user->can('View:Branch')) {
            return false;
        }

        return in_array((int) $this->getKey(), BranchAccess::accessibleBranchIds($user, false), true);
    }

    protected static function booted(): void
    {
        static::saving(function (self $branch): void {
            if (filled($branch->code)) {
                return;
            }

            $branch->code = self::generateUniqueCode();
        });
    }

    protected static function generateUniqueCode(): string
    {
        $date = now()->format('Ymd');

        do {
            $suffix = Str::upper(Str::random(6));
            $code = "BR-{$date}-{$suffix}";
        } while (self::query()->withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
