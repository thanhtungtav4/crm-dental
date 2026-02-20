<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMedicalRecord extends Model
{
    protected $fillable = [
        'patient_id',
        'allergies',
        'chronic_diseases',
        'current_medications',
        'insurance_provider',
        'insurance_number',
        'insurance_expiry_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_email',
        'emergency_contact_relationship',
        'blood_type',
        'additional_notes',
        'updated_by',
    ];

    protected $casts = [
        'allergies' => 'array',
        'chronic_diseases' => 'array',
        'current_medications' => 'array',
        'insurance_expiry_date' => 'date',
    ];

    /**
     * Patient this record belongs to
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * User who last updated this record
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if patient has any allergies
     */
    public function hasAllergies(): bool
    {
        return !empty($this->allergies) && count($this->allergies) > 0;
    }

    /**
     * Check if patient has chronic diseases
     */
    public function hasChronicDiseases(): bool
    {
        return !empty($this->chronic_diseases) && count($this->chronic_diseases) > 0;
    }

    /**
     * Check if insurance is expired
     */
    public function isInsuranceExpired(): bool
    {
        if (!$this->insurance_expiry_date) {
            return false;
        }
        
        return $this->insurance_expiry_date < now();
    }
}
