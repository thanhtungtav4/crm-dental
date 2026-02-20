<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'treatment_session_id',
        'prescription_code',
        'prescription_name',
        'doctor_id',
        'treatment_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'treatment_date' => 'date',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function treatmentSession(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Generate unique prescription code
    public static function generatePrescriptionCode(): string
    {
        $prefix = 'DT';
        $date = now()->format('ymd');

        $lastPrescription = static::withTrashed()
            ->whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPrescription) {
            $lastNumber = (int) substr($lastPrescription->prescription_code, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $date . $newNumber;
    }

    // Boot method to auto-generate code
    protected static function booted(): void
    {
        static::creating(function (Prescription $prescription) {
            if (empty($prescription->prescription_code)) {
                $prescription->prescription_code = static::generatePrescriptionCode();
            }

            if (empty($prescription->created_by) && auth()->check()) {
                $prescription->created_by = auth()->id();
            }
        });
    }

    // Get total medications count
    public function getTotalMedicationsAttribute(): int
    {
        return $this->items()->count();
    }

    // Scopes
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByDoctor($query, int $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('treatment_date', $date);
    }
}
