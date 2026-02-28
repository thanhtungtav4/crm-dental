<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalNote extends Model
{
    protected $fillable = [
        'patient_id',
        'visit_episode_id',
        'doctor_id',
        'examining_doctor_id',
        'treating_doctor_id',
        'branch_id',
        'date',
        'examination_note',
        'general_exam_notes',
        'recommendation_notes',
        'treatment_plan_note',
        'indications',
        'indication_images',
        'diagnoses',
        'tooth_diagnosis_data',
        'other_diagnosis',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'visit_episode_id' => 'integer',
        'date' => 'date',
        'indications' => 'array',
        'indication_images' => 'array',
        'diagnoses' => 'array',
        'tooth_diagnosis_data' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitEpisode()
    {
        return $this->belongsTo(VisitEpisode::class, 'visit_episode_id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'visit_episode_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function examiningDoctor()
    {
        return $this->belongsTo(User::class, 'examining_doctor_id');
    }

    public function treatingDoctor()
    {
        return $this->belongsTo(User::class, 'treating_doctor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
