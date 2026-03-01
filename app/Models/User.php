<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasPasskeys
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithPasskeys, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'gender',
        'phone',
        'specialty',
        'avatar',
        'branch_id',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctorBranchAssignments(): HasMany
    {
        return $this->hasMany(DoctorBranchAssignment::class);
    }

    public function activeDoctorBranchAssignments(): HasMany
    {
        return $this->doctorBranchAssignments()->where('is_active', true);
    }

    public function assignedBranches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'doctor_branch_assignments',
            'user_id',
            'branch_id',
        )
            ->withPivot([
                'is_active',
                'is_primary',
                'assigned_from',
                'assigned_until',
                'created_by',
                'note',
            ])
            ->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (array_key_exists('status', $this->getAttributes()) && ! (bool) $this->status) {
            return false;
        }

        if ($panel->getId() !== 'admin') {
            return true;
        }

        return $this->hasAnyRole(['Admin', 'Manager', 'Doctor', 'CSKH']);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleBranchIds(): array
    {
        if ($this->hasRole('Admin')) {
            return [];
        }

        $branchIds = collect([$this->branch_id])
            ->filter()
            ->map(static fn (mixed $branchId): int => (int) $branchId);

        $assignmentBranchIds = $this->activeDoctorBranchAssignments()
            ->pluck('branch_id')
            ->map(static fn (mixed $branchId): int => (int) $branchId);

        return $branchIds
            ->merge($assignmentBranchIds)
            ->unique()
            ->values()
            ->all();
    }

    public function canAccessBranch(?int $branchId): bool
    {
        if ($this->hasRole('Admin')) {
            return true;
        }

        if ($branchId === null) {
            return false;
        }

        return in_array($branchId, $this->accessibleBranchIds(), true);
    }

    public function hasAnyAccessibleBranch(): bool
    {
        if ($this->hasRole('Admin')) {
            return true;
        }

        return count($this->accessibleBranchIds()) > 0;
    }
}
