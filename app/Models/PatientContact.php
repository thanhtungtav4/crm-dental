<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PatientContact extends Model
{
    protected $fillable = [
        'patient_id',
        'full_name',
        'relationship',
        'phone',
        'email',
        'is_primary',
        'is_emergency',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'is_primary' => 'boolean',
        'is_emergency' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $contact): void {
            if (! $contact->patient_id) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Liên hệ bắt buộc thuộc về một bệnh nhân.',
                ]);
            }

            $patientBranchId = Patient::query()
                ->whereKey((int) $contact->patient_id)
                ->value('first_branch_id');

            if (is_numeric($patientBranchId)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $patientBranchId,
                    field: 'patient_id',
                    message: 'Bạn không có quyền thao tác người liên hệ ở chi nhánh này.',
                );
            }

            if ($contact->is_primary) {
                static::query()
                    ->where('patient_id', (int) $contact->patient_id)
                    ->when($contact->exists, fn (Builder $query) => $query->where('id', '!=', $contact->id))
                    ->update(['is_primary' => false]);
            }

            if (auth()->check()) {
                $contact->updated_by = auth()->id();

                if (! $contact->exists) {
                    $contact->created_by = auth()->id();
                }
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
