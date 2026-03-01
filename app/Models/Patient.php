<?php

namespace App\Models;

use App\Casts\NullableEncrypted;
use App\Support\ClinicRuntimeSettings;
use App\Support\PatientCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    public ?string $branchTransferLogNote = null;

    public ?int $branchTransferActorId = null;

    protected $fillable = [
        'customer_id',
        'patient_code',
        'first_branch_id',
        'full_name',
        'email',
        'birthday',
        'cccd',
        'gender',
        'phone',
        'phone_secondary',
        'occupation',
        'address',
        'customer_group_id',
        'promotion_group_id',
        'primary_doctor_id',
        'owner_staff_id',
        'first_visit_reason',
        'note',
        'status',
        'medical_history',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birthday' => 'date',
        'last_verified_at' => 'datetime',
        'medical_history' => NullableEncrypted::class,
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'first_branch_id');
    }

    public function treatmentPlans()
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function branchLogs()
    {
        return $this->hasMany(BranchLog::class);
    }

    public function branchTransferRequests()
    {
        return $this->hasMany(BranchTransferRequest::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function visitEpisodes()
    {
        return $this->hasMany(VisitEpisode::class);
    }

    public function encounters()
    {
        return $this->hasMany(Encounter::class, 'patient_id');
    }

    public function payments()
    {
        return $this->hasManyThrough(
            Payment::class,
            Invoice::class,
            'patient_id',
            'invoice_id'
        );
    }

    public function clinicalNotes()
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function photos()
    {
        return $this->hasMany(PatientPhoto::class);
    }

    public function treatmentSessions()
    {
        return $this->hasManyThrough(
            TreatmentSession::class,
            TreatmentPlan::class,
            'patient_id',
            'treatment_plan_id'
        );
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function clinicalOrders()
    {
        return $this->hasMany(ClinicalOrder::class);
    }

    public function clinicalResults()
    {
        return $this->hasMany(ClinicalResult::class);
    }

    public function medicalRecord()
    {
        return $this->hasOne(PatientMedicalRecord::class);
    }

    public function consents()
    {
        return $this->hasMany(Consent::class);
    }

    public function insuranceClaims()
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function installmentPlans()
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    public function loyalty()
    {
        return $this->hasOne(PatientLoyalty::class);
    }

    public function riskProfiles()
    {
        return $this->hasMany(PatientRiskProfile::class);
    }

    public function latestRiskProfile()
    {
        return $this->hasOne(PatientRiskProfile::class)
            ->latestOfMany('as_of_date');
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function promotionGroup()
    {
        return $this->belongsTo(PromotionGroup::class);
    }

    public function primaryDoctor()
    {
        return $this->belongsTo(User::class, 'primary_doctor_id');
    }

    public function ownerStaff()
    {
        return $this->belongsTo(User::class, 'owner_staff_id');
    }

    public function getGenderLabel(): string
    {
        return ClinicRuntimeSettings::genderLabel($this->gender);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $patient) {
            // Prevent any deletion (soft or force) via model events
            return false;
        });

        static::creating(function (self $patient) {
            // If no customer selected, create a lead automatically from patient info
            if (empty($patient->customer_id)) {
                $customer = new Customer;
                $customer->branch_id = $patient->first_branch_id;
                $customer->full_name = $patient->full_name;
                $customer->phone = $patient->phone;
                $customer->email = $patient->email ?? null;
                $customer->source = ClinicRuntimeSettings::defaultCustomerSource();
                $customer->customer_group_id = $patient->customer_group_id ?? null;
                $customer->promotion_group_id = $patient->promotion_group_id ?? null;
                $customer->status = ClinicRuntimeSettings::defaultCustomerStatus();
                $customer->notes = $patient->note ?: ($patient->first_visit_reason ?: 'Auto-created from Patient');
                $customer->assigned_to = $patient->owner_staff_id ?? null;
                $customer->save();

                $patient->customer_id = $customer->id;
            }
            if (! empty($patient->patient_code)) {
                return;
            }

            $patient->patient_code = PatientCodeGenerator::generate();
        });

        static::updating(function (self $patient) {
            if ($patient->isDirty('first_branch_id')) {
                $from = $patient->getOriginal('first_branch_id');
                $to = $patient->first_branch_id;
                if ($from != $to) {
                    \App\Models\BranchLog::create([
                        'patient_id' => $patient->id,
                        'from_branch_id' => $from,
                        'to_branch_id' => $to,
                        'moved_by' => $patient->branchTransferActorId ?? Auth::id(),
                        'note' => $patient->branchTransferLogNote ?: 'Chuyển chi nhánh',
                    ]);
                }
            }
        });
    }
}
