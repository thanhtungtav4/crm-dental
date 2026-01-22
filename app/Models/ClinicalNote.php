<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Patient;
use App\Models\User;
use App\Models\Branch;

class ClinicalNote extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'branch_id',
        'date',
        'examination_note',
        'treatment_plan_note',
        'indications',
        'diagnoses',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'indications' => 'array',
        'diagnoses' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
