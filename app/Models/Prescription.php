<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'branch_id',
        'visit_episode_id',
        'treatment_session_id',
        'prescription_code',
        'prescription_name',
        'doctor_id',
        'treatment_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'visit_episode_id' => 'integer',
        'treatment_date' => 'date',
        'notes' => 'encrypted',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function treatmentSession(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class);
    }

    public function visitEpisode(): BelongsTo
    {
        return $this->belongsTo(VisitEpisode::class, 'visit_episode_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'visit_episode_id');
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

        return $prefix.$date.$newNumber;
    }

    // Boot method to auto-generate code
    protected static function booted(): void
    {
        static::creating(function (Prescription $prescription) {
            if (blank($prescription->branch_id)) {
                $prescription->branch_id = static::inferBranchId($prescription);
            }

            if (blank($prescription->visit_episode_id)) {
                $prescription->visit_episode_id = static::inferVisitEpisodeId($prescription);
            }

            if (empty($prescription->prescription_code)) {
                $prescription->prescription_code = static::generatePrescriptionCode();
            }

            if (empty($prescription->created_by) && auth()->check()) {
                $prescription->created_by = auth()->id();
            }
        });
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->branch_id
            ?? $this->visitEpisode?->branch_id
            ?? $this->patient?->first_branch_id
            ?? $this->treatmentSession?->treatmentPlan?->branch_id;

        return $branchId !== null ? (int) $branchId : null;
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

    protected static function inferBranchId(self $prescription): ?int
    {
        if ($prescription->visit_episode_id) {
            $episodeBranchId = VisitEpisode::query()
                ->whereKey((int) $prescription->visit_episode_id)
                ->value('branch_id');

            if ($episodeBranchId !== null) {
                return (int) $episodeBranchId;
            }
        }

        if ($prescription->patient_id) {
            $patientBranchId = Patient::query()
                ->whereKey((int) $prescription->patient_id)
                ->value('first_branch_id');

            if ($patientBranchId !== null) {
                return (int) $patientBranchId;
            }
        }

        if (! $prescription->treatment_session_id) {
            return null;
        }

        $sessionBranchId = TreatmentSession::query()
            ->join('treatment_plans', 'treatment_plans.id', '=', 'treatment_sessions.treatment_plan_id')
            ->where('treatment_sessions.id', (int) $prescription->treatment_session_id)
            ->value('treatment_plans.branch_id');

        return $sessionBranchId !== null ? (int) $sessionBranchId : null;
    }

    protected static function inferVisitEpisodeId(self $prescription): ?int
    {
        if (! $prescription->patient_id) {
            return null;
        }

        $branchId = $prescription->branch_id
            ?? static::inferBranchId($prescription);

        $encounterDate = $prescription->treatment_date?->toDateString();
        if ($encounterDate === null) {
            $encounterDate = now()->toDateString();
        }

        $query = VisitEpisode::query()
            ->where('patient_id', (int) $prescription->patient_id)
            ->whereDate('scheduled_at', $encounterDate);

        if ($branchId !== null) {
            $query->where('branch_id', (int) $branchId);
        }

        $episodeId = $query
            ->orderByRaw('appointment_id IS NULL')
            ->orderByDesc('scheduled_at')
            ->value('id');

        return $episodeId !== null ? (int) $episodeId : null;
    }
}
