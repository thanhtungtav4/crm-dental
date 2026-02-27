<?php

namespace App\Models;

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
