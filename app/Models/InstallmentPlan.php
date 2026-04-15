<?php

namespace App\Models;

use App\Services\InstallmentPlanLifecycleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class InstallmentPlan extends Model
{
    use HasFactory;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $scheduleModalViewStateCache = null;

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

    public function syncFinancialState(?Carbon $asOf = null, bool $persist = true): self
    {
        return app(InstallmentPlanLifecycleService::class)->syncFinancialState($this, $asOf, $persist);
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

    public function scheduleModalHasSchedule(): bool
    {
        return $this->scheduleModalViewState()['has_schedule'];
    }

    /**
     * @return array{
     *     has_schedule:bool,
     *     rows:list<array<string, mixed>>,
     *     total_amount_label:string,
     *     paid_progress_label:string,
     *     summary_cards:list<array{label:string,value:string,value_classes:string}>,
     *     empty_state:array{title:string,description:string}
     * }
     */
    public function scheduleModalView(): array
    {
        return $this->scheduleModalViewState();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scheduleModalRows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->scheduleModalViewState()['rows'];

        return $rows;
    }

    public function scheduleModalTotalAmountLabel(): string
    {
        return $this->scheduleModalViewState()['total_amount_label'];
    }

    public function scheduleModalPaidProgressLabel(): string
    {
        return $this->scheduleModalViewState()['paid_progress_label'];
    }

    /**
     * @return list<array{label:string,value:string,value_classes:string}>
     */
    public function scheduleModalSummaryCards(): array
    {
        /** @var list<array{label:string,value:string,value_classes:string}> $summaryCards */
        $summaryCards = $this->scheduleModalViewState()['summary_cards'];

        return $summaryCards;
    }

    /**
     * @return array{title:string,description:string}
     */
    public function scheduleModalEmptyState(): array
    {
        /** @var array{title:string,description:string} $emptyState */
        $emptyState = $this->scheduleModalViewState()['empty_state'];

        return $emptyState;
    }

    public function getCompletionPercentage(): int
    {
        $schedule = collect($this->schedule ?? []);
        $installmentCount = $schedule->count();

        if ($installmentCount === 0) {
            return 0;
        }

        $paidInstallmentCount = $schedule
            ->filter(fn (array $installment): bool => ($installment['status'] ?? 'pending') === 'paid')
            ->count();

        return (int) round(($paidInstallmentCount / $installmentCount) * 100);
    }

    /**
     * @return array{
     *     has_schedule:bool,
     *     rows:list<array<string, mixed>>,
     *     total_amount_label:string,
     *     paid_progress_label:string,
     *     summary_cards:list<array{label:string,value:string,value_classes:string}>,
     *     empty_state:array{title:string,description:string}
     * }
     */
    protected function scheduleModalViewState(): array
    {
        if ($this->scheduleModalViewStateCache !== null) {
            return $this->scheduleModalViewStateCache;
        }

        $schedule = collect($this->schedule ?? [])
            ->filter(fn ($installment): bool => is_array($installment))
            ->values();
        $paidInstallmentCount = $schedule
            ->filter(fn (array $installment): bool => ($installment['status'] ?? 'pending') === 'paid')
            ->count();

        return $this->scheduleModalViewStateCache = [
            'has_schedule' => $schedule->isNotEmpty(),
            'rows' => $schedule
                ->map(fn (array $installment): array => $this->scheduleModalRow($installment))
                ->all(),
            'total_amount_label' => $this->formatMoneyLabel($this->scheduleTotalAmount($schedule)),
            'paid_progress_label' => $paidInstallmentCount.' / '.$schedule->count().' kỳ',
            'summary_cards' => [
                [
                    'label' => 'Đã thanh toán',
                    'value' => $this->formatMoneyLabel($this->schedulePaidAmount($schedule)),
                    'value_classes' => 'text-lg font-bold text-green-600',
                ],
                [
                    'label' => 'Còn lại',
                    'value' => $this->formatMoneyLabel((float) $this->remaining_amount),
                    'value_classes' => 'text-lg font-bold text-red-600',
                ],
                [
                    'label' => 'Tiến độ',
                    'value' => $this->getCompletionPercentage().'%',
                    'value_classes' => 'text-lg font-bold text-blue-600',
                ],
            ],
            'empty_state' => [
                'title' => 'Chưa có lịch trả góp',
                'description' => 'Lịch trả góp sẽ được tạo tự động sau khi lưu kế hoạch',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $installment
     * @return array<string, mixed>
     */
    protected function scheduleModalRow(array $installment): array
    {
        $dueDate = Carbon::parse((string) ($installment['due_date'] ?? now()->toDateString()));
        $status = (string) ($installment['status'] ?? 'pending');
        $isPaid = $status === 'paid';
        $isPast = $dueDate->isPast();
        $isNear = $dueDate->diffInDays(now(), false) <= 7 && ! $isPast;
        $isOverdue = $status === 'overdue' || ($isPast && ! $isPaid);

        return [
            'installment_label' => 'Kỳ '.($installment['installment_number'] ?? $installment['index'] ?? '?'),
            'due_date_label' => $dueDate->format('d/m/Y'),
            'due_date_classes' => $isOverdue ? 'text-red-600 font-semibold' : '',
            'show_near_due_notice' => $isNear && ! $isPaid,
            'near_due_label' => '⏰ Sắp đến hạn',
            'amount_label' => $this->formatMoneyLabel((float) ($installment['amount'] ?? 0)),
            'status_label' => match (true) {
                $isPaid => '✓ Đã thanh toán',
                $isOverdue => '⚠ Quá hạn',
                default => '○ Chờ thanh toán',
            },
            'status_classes' => match (true) {
                $isPaid => 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800',
                $isOverdue => 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800',
                default => 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800',
            },
        ];
    }

    protected function formatMoneyLabel(float $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $schedule
     */
    protected function scheduleTotalAmount(\Illuminate\Support\Collection $schedule): float
    {
        $scheduleTotal = (float) $schedule->sum(
            fn (array $installment): float => round((float) ($installment['amount'] ?? 0), 2),
        );

        if ($scheduleTotal > 0) {
            return $scheduleTotal;
        }

        return round((float) $this->financed_amount, 2);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $schedule
     */
    protected function schedulePaidAmount(\Illuminate\Support\Collection $schedule): float
    {
        $schedulePaidAmount = (float) $schedule->sum(
            fn (array $installment): float => round((float) ($installment['paid_amount'] ?? 0), 2),
        );

        if ($schedulePaidAmount > 0) {
            return $schedulePaidAmount;
        }

        return max(0, round((float) $this->financed_amount - (float) $this->remaining_amount, 2));
    }
}
