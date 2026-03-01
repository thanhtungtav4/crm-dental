<?php

namespace App\Models;

use App\Services\PatientConversionService;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'full_name',
        'phone',
        'phone_normalized',
        'email',
        'birthday',
        'gender',
        'address',
        'source',
        'source_detail',
        'customer_group_id',
        'promotion_group_id',
        'status',
        'assigned_to',
        'next_follow_up_at',
        'last_contacted_at',
        'last_web_contact_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birthday' => 'date',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'last_web_contact_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $customer): void {
            if (! is_numeric($customer->branch_id)) {
                return;
            }

            BranchAccess::assertCanAccessBranch(
                branchId: (int) $customer->branch_id,
                field: 'branch_id',
                message: 'Bạn không có quyền thao tác khách hàng ở chi nhánh này.',
            );
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function promotionGroup()
    {
        return $this->belongsTo(PromotionGroup::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function convertToPatient(): Patient
    {
        /** @var PatientConversionService $service */
        $service = app(PatientConversionService::class);
        $patient = $service->convert($this);

        if (! $patient) {
            throw new RuntimeException('Không thể chuyển đổi khách hàng thành bệnh nhân.');
        }

        return $patient;
    }
}
