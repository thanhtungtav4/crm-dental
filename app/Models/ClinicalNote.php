<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalNote extends Model
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $revisionPreviousPayload = null;

    public ?string $revisionOperation = null;

    public ?string $revisionReason = null;

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
        'lock_version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'visit_episode_id' => 'integer',
        'lock_version' => 'integer',
        'date' => 'date',
        'examination_note' => 'encrypted',
        'general_exam_notes' => 'encrypted',
        'recommendation_notes' => 'encrypted',
        'treatment_plan_note' => 'encrypted',
        'other_diagnosis' => 'encrypted',
        'indications' => 'array',
        'indication_images' => 'array',
        'diagnoses' => 'array',
        'tooth_diagnosis_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $clinicalNote): void {
            if (! $clinicalNote->lock_version || (int) $clinicalNote->lock_version < 1) {
                $clinicalNote->lock_version = 1;
            }
        });

        static::updating(function (self $clinicalNote): void {
            if (! $clinicalNote->hasTrackedRevisionChanges()) {
                return;
            }

            if ($clinicalNote->revisionPreviousPayload === null) {
                $clinicalNote->revisionPreviousPayload = self::query()
                    ->whereKey($clinicalNote->id)
                    ->first()?->revisionPayload();
            }

            $clinicalNote->lock_version = ((int) $clinicalNote->getOriginal('lock_version')) + 1;
        });
    }

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

    public function clinicalOrders(): HasMany
    {
        return $this->hasMany(ClinicalOrder::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ClinicalNoteRevision::class);
    }

    /**
     * @return array<int, string>
     */
    public static function trackedRevisionFields(): array
    {
        return [
            'date',
            'visit_episode_id',
            'examining_doctor_id',
            'treating_doctor_id',
            'general_exam_notes',
            'treatment_plan_note',
            'indications',
            'indication_images',
            'tooth_diagnosis_data',
            'other_diagnosis',
        ];
    }

    public function hasTrackedRevisionChanges(): bool
    {
        return $this->isDirty(self::trackedRevisionFields());
    }

    /**
     * @return array<string, mixed>
     */
    public function revisionPayload(): array
    {
        return [
            'date' => $this->date?->toDateString(),
            'visit_episode_id' => $this->visit_episode_id ? (int) $this->visit_episode_id : null,
            'examining_doctor_id' => $this->examining_doctor_id ? (int) $this->examining_doctor_id : null,
            'treating_doctor_id' => $this->treating_doctor_id ? (int) $this->treating_doctor_id : null,
            'general_exam_notes' => $this->general_exam_notes,
            'treatment_plan_note' => $this->treatment_plan_note,
            'indications' => array_values((array) ($this->indications ?? [])),
            'indication_images' => (array) ($this->indication_images ?? []),
            'tooth_diagnosis_data' => (array) ($this->tooth_diagnosis_data ?? []),
            'other_diagnosis' => $this->other_diagnosis,
        ];
    }

    public function scopeCurrentVersion(Builder $query): Builder
    {
        return $query->where('lock_version', '>=', 1);
    }
}
