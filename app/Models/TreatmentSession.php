<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'treatment_plan_id',
        'plan_item_id',
        'doctor_id',
        'assistant_id',
        'start_at',
        'end_at',
        'performed_at',
        'diagnosis',
        'procedure',
        'images',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'images' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'performed_at' => 'datetime',
    ];

    public function treatmentPlan()
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function planItem()
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function assistant()
    {
        return $this->belongsTo(User::class, 'assistant_id');
    }

    public function materials()
    {
        return $this->hasMany(TreatmentMaterial::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->treatmentPlan?->branch_id
            ?? $this->planItem?->resolveBranchId();

        return $branchId !== null ? (int) $branchId : null;
    }
}
