<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        static::creating(function (self $branch) {
            if (! empty($branch->code)) {
                return;
            }

            // Generate unique code: BR-YYYYMMDD-XXXXXX
            $date = now()->format('Ymd');
            $attempts = 0;
            do {
                $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                $code = "BR-{$date}-{$suffix}";
                $exists = self::where('code', $code)->withTrashed()->exists();
                $attempts++;
            } while ($exists && $attempts < 5);

            $branch->code = $code;
        });
    }
}
