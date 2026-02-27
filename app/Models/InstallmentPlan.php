<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class InstallmentPlan extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DEFAULTED = 'defaulted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'invoice_id',
        'patient_id',
        'branch_id',
        'plan_code',
        'financed_amount',
        'down_payment_amount',
        'remaining_amount',
        'number_of_installments',
        'installment_amount',
        'start_date',
        'next_due_date',
        'end_date',
        'status',
        'schedule',
        'dunning_level',
        'last_dunned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'financed_amount' => 'decimal:2',
            'down_payment_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'start_date' => 'date',
            'next_due_date' => 'date',
            'end_date' => 'date',
            'schedule' => 'array',
            'last_dunned_at' => 'datetime',
            'dunning_level' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            if (blank($plan->plan_code)) {
                $prefix = 'INS-'.now()->format('Ymd').'-';
                $plan->plan_code = Cache::lock("installment_plan:{$prefix}", 5)
                    ->block(5, function () use ($prefix): string {
                        $latestCode = self::query()
                            ->where('plan_code', 'like', $prefix.'%')
                            ->orderByDesc('plan_code')
                            ->value('plan_code');

                        $sequence = 1;
                        if (is_string($latestCode) && preg_match('/(\d{4})$/', $latestCode, $matches) === 1) {
                            $sequence = ((int) $matches[1]) + 1;
                        }

                        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                    });
            }

            if (blank($plan->schedule)) {
                $plan->schedule = self::buildSchedule(
                    $plan->start_date ?? now(),
                    max(1, (int) $plan->number_of_installments),
                    (float) $plan->installment_amount,
                );
            }

            if (blank($plan->remaining_amount)) {
                $plan->remaining_amount = max(0, (float) $plan->financed_amount);
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function buildSchedule(Carbon|string $startDate, int $count, float $amount): array
    {
        $date = $startDate instanceof Carbon ? $startDate->copy()->startOfDay() : Carbon::parse($startDate)->startOfDay();
        $schedule = [];

        for ($index = 1; $index <= max(1, $count); $index++) {
            $schedule[] = [
                'index' => $index,
                'due_date' => $date->copy()->addMonthsNoOverflow($index - 1)->toDateString(),
                'amount' => round(max(0, $amount), 2),
                'paid_amount' => 0,
                'status' => 'pending',
                'paid_at' => null,
            ];
        }

        return $schedule;
    }

    public function syncFinancialState(?Carbon $asOf = null, bool $persist = true): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        $asOfDate = ($asOf ?? now())->copy()->startOfDay();
        $paidAmount = (float) ($this->invoice?->getTotalPaid() ?? 0);
        $paidTowardsInstallment = max(0, $paidAmount - (float) $this->down_payment_amount);

        $schedule = $this->schedule ?? [];
        $remainingPaid = $paidTowardsInstallment;
        $nextDueDate = null;
        $hasOverdue = false;

        foreach ($schedule as $index => $installment) {
            $installmentAmount = round(max(0, (float) ($installment['amount'] ?? 0)), 2);
            $paidForInstallment = min($installmentAmount, $remainingPaid);
            $remainingPaid = round(max(0, $remainingPaid - $paidForInstallment), 2);

            $dueDate = Carbon::parse((string) ($installment['due_date'] ?? $asOfDate->toDateString()))->startOfDay();
            $isPaid = $paidForInstallment >= $installmentAmount;
            $isOverdue = ! $isPaid && $dueDate->lt($asOfDate);

            if (! $isPaid && $nextDueDate === null) {
                $nextDueDate = $dueDate;
            }

            if ($isOverdue) {
                $hasOverdue = true;
            }

            $schedule[$index]['paid_amount'] = $paidForInstallment;
            $schedule[$index]['status'] = $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'pending');
            $schedule[$index]['paid_at'] = $isPaid ? ($schedule[$index]['paid_at'] ?? $asOfDate->toDateString()) : null;
        }

        $remainingAmount = round(max(0, (float) $this->financed_amount - $paidTowardsInstallment), 2);

        $this->schedule = $schedule;
        $this->remaining_amount = $remainingAmount;
        $this->next_due_date = $nextDueDate?->toDateString();

        if ($remainingAmount <= 0.0) {
            $this->status = self::STATUS_COMPLETED;
            $this->dunning_level = 0;
        } elseif ($hasOverdue) {
            $this->status = self::STATUS_DEFAULTED;
        } else {
            $this->status = self::STATUS_ACTIVE;
            $this->dunning_level = 0;
        }

        if ($persist) {
            $this->save();
        }
    }

    public function getCurrentAgingBucket(?Carbon $asOf = null): int
    {
        $asOfDate = ($asOf ?? now())->copy()->startOfDay();

        if (! $this->next_due_date) {
            return 0;
        }

        $daysPastDue = Carbon::parse($this->next_due_date)
            ->startOfDay()
            ->diffInDays($asOfDate, false);

        if ($daysPastDue <= 0) {
            return 0;
        }

        if ($daysPastDue <= 3) {
            return 1;
        }

        if ($daysPastDue <= 7) {
            return 2;
        }

        return 3;
    }

    public function shouldRunDunning(?Carbon $asOf = null): bool
    {
        if ($this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_CANCELLED) {
            return false;
        }

        $bucket = $this->getCurrentAgingBucket($asOf);

        return $bucket > 0 && $bucket > (int) $this->dunning_level;
    }
}
