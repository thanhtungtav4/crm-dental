<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientWallet extends Model
{
    protected $fillable = [
        'patient_id',
        'branch_id',
        'balance',
        'total_deposit',
        'total_spent',
        'total_refunded',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'branch_id' => 'integer',
            'balance' => 'decimal:2',
            'total_deposit' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'total_refunded' => 'decimal:2',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $wallet): void {
            if (blank($wallet->branch_id)) {
                $wallet->branch_id = Patient::query()
                    ->whereKey((int) $wallet->patient_id)
                    ->value('first_branch_id');
            }

            if (is_numeric($wallet->branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $wallet->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác ví bệnh nhân ở chi nhánh này.',
                );
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    public function scopeBranchAccessible(Builder $query): Builder
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = $authUser->accessibleBranchIds();
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }
}
