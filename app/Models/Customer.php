<?php

namespace App\Models;

use App\Services\PatientConversionService;
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
        'email',
        'birthday',
        'gender',
        'address',
        'source',
        'customer_group_id',
        'promotion_group_id',
        'status',
        'assigned_to',
        'next_follow_up_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birthday' => 'date',
    ];

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
