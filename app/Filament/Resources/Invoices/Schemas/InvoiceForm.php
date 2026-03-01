<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Support\BranchAccess;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Thông tin hóa đơn')
                    ->schema([
                        Select::make('patient_id')
                            ->label('Bệnh nhân')
                            ->relationship(
                                name: 'patient',
                                titleAttribute: 'full_name',
                                modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeQueryByAccessibleBranches($query, 'first_branch_id'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => request()->integer('patient_id') ?: null)
                            ->live()
                            ->afterStateHydrated(function ($state, Set $set): void {
                                if (is_numeric($state)) {
                                    return;
                                }

                                $patientId = request()->integer('patient_id');
                                if ($patientId) {
                                    $set('patient_id', $patientId);
                                }
                            })
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $patientId = is_numeric($state) ? (int) $state : null;
                                if (! $patientId) {
                                    $set('treatment_plan_id', null);
                                    $set('treatment_session_id', null);

                                    return;
                                }

                                $planId = $get('treatment_plan_id');
                                if (! is_numeric($planId)) {
                                    return;
                                }

                                $belongsToPatient = TreatmentPlan::query()
                                    ->whereKey((int) $planId)
                                    ->where('patient_id', $patientId)
                                    ->exists();

                                if (! $belongsToPatient) {
                                    $set('treatment_plan_id', null);
                                    $set('treatment_session_id', null);
                                }
                            })
                            ->columnSpan(1),

                        Select::make('treatment_plan_id')
                            ->label('Kế hoạch điều trị')
                            ->options(fn (Get $get): array => self::planOptionsForPatient($get('patient_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->default(fn (): ?int => request()->integer('treatment_plan_id') ?: null)
                            ->afterStateHydrated(function ($state, Set $set, Get $get): void {
                                if (! is_numeric($state)) {
                                    return;
                                }

                                $plan = TreatmentPlan::query()
                                    ->with('planItems:id,treatment_plan_id,quantity,price,final_amount')
                                    ->find((int) $state);

                                if (! $plan) {
                                    return;
                                }

                                if (! is_numeric($get('patient_id'))) {
                                    $set('patient_id', $plan->patient_id);
                                }

                                if (self::normalizeAmount($get('subtotal')) === 0.0) {
                                    $subtotal = self::resolvePlanSubtotal($plan);
                                    $set('subtotal', $subtotal);
                                    $set('total_amount', Invoice::calculateTotalAmount(
                                        $subtotal,
                                        self::normalizeAmount($get('discount_amount')),
                                        self::normalizeAmount($get('tax_amount'))
                                    ));
                                }
                            })
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                if (! is_numeric($state)) {
                                    $set('treatment_session_id', null);

                                    return;
                                }

                                $plan = TreatmentPlan::query()
                                    ->with('planItems:id,treatment_plan_id,quantity,price,final_amount')
                                    ->find((int) $state);

                                if (! $plan) {
                                    return;
                                }

                                $set('patient_id', $plan->patient_id);
                                $set('treatment_session_id', null);

                                $subtotal = self::resolvePlanSubtotal($plan);
                                $set('subtotal', $subtotal);
                                $set('total_amount', Invoice::calculateTotalAmount(
                                    $subtotal,
                                    self::normalizeAmount($get('discount_amount')),
                                    self::normalizeAmount($get('tax_amount'))
                                ));
                            })
                            ->columnSpan(1),

                        Select::make('treatment_session_id')
                            ->label('Phiên điều trị')
                            ->options(fn (Get $get): array => self::sessionOptionsForPlan($get('treatment_plan_id')))
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        TextInput::make('invoice_no')
                            ->label('Số hóa đơn')
                            ->required()
                            ->maxLength(50)
                            ->default(fn (): string => Invoice::generateInvoiceNo())
                            ->unique(table: 'invoices', column: 'invoice_no', ignoreRecord: true)
                            ->columnSpan(1),

                        Select::make('status')
                            ->label('Trạng thái')
                            ->options(Invoice::formStatusOptions())
                            ->default(Invoice::STATUS_DRAFT)
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Trạng thái đã thu / quá hạn sẽ tự động cập nhật theo thanh toán.')
                            ->columnSpan(1),

                        DateTimePicker::make('issued_at')
                            ->label('Ngày xuất hóa đơn')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->default(now())
                            ->visible(fn (Get $get): bool => $get('status') !== Invoice::STATUS_DRAFT)
                            ->required(fn (Get $get): bool => $get('status') !== Invoice::STATUS_DRAFT)
                            ->columnSpan(1),

                        DatePicker::make('due_date')
                            ->label('Hạn thanh toán')
                            ->native(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Giá trị hóa đơn')
                    ->schema([
                        TextInput::make('subtotal')
                            ->label('Tạm tính')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('VNĐ')
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncTotalAmount($set, $get)),

                        TextInput::make('discount_amount')
                            ->label('Giảm giá')
                            ->numeric()
                            ->default(0)
                            ->prefix('VNĐ')
                            ->live()
                            ->afterStateHydrated(fn ($state, Set $set, Get $get) => self::syncTotalAmount($set, $get))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncTotalAmount($set, $get)),

                        TextInput::make('tax_amount')
                            ->label('Thuế / phụ phí')
                            ->numeric()
                            ->default(0)
                            ->prefix('VNĐ')
                            ->live()
                            ->afterStateHydrated(fn ($state, Set $set, Get $get) => self::syncTotalAmount($set, $get))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncTotalAmount($set, $get)),

                        TextInput::make('total_amount')
                            ->label('Tổng thanh toán')
                            ->numeric()
                            ->required()
                            ->prefix('VNĐ')
                            ->readOnly()
                            ->default(0)
                            ->helperText('Tổng = Tạm tính - Giảm giá + Thuế/phụ phí'),

                        Placeholder::make('payment_snapshot')
                            ->label('Đã thu / Còn lại')
                            ->content(function ($record, Get $get): string {
                                $paidAmount = self::normalizeAmount($record?->paid_amount ?? 0);
                                $totalAmount = self::normalizeAmount($get('total_amount') ?? $record?->total_amount ?? 0);
                                $balance = max(0, round($totalAmount - $paidAmount, 2));

                                return sprintf(
                                    '%sđ / %sđ',
                                    number_format($paidAmount, 0, ',', '.'),
                                    number_format($balance, 0, ',', '.')
                                );
                            }),

                        Placeholder::make('export_status')
                            ->label('Trạng thái xuất hóa đơn')
                            ->content(function ($record): string {
                                if (! $record) {
                                    return 'Chưa xuất hóa đơn';
                                }

                                if (! $record->invoice_exported) {
                                    return 'Chưa xuất hóa đơn';
                                }

                                $exportedAt = $record->exported_at
                                    ? Carbon::parse($record->exported_at)->format('d/m/Y H:i')
                                    : '-';

                                return "Đã xuất lúc {$exportedAt}";
                            })
                            ->visible(fn ($operation): bool => $operation === 'edit'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function planOptionsForPatient(mixed $patientId): array
    {
        $query = TreatmentPlan::query()
            ->select(['id', 'title', 'status', 'created_at', 'patient_id'])
            ->orderByDesc('created_at');

        BranchAccess::scopeQueryByAccessibleBranches($query, 'branch_id');

        if (is_numeric($patientId)) {
            $query->where('patient_id', (int) $patientId);
        }

        return $query
            ->limit(100)
            ->get()
            ->mapWithKeys(function (TreatmentPlan $plan): array {
                $label = "{$plan->title} ({$plan->getStatusLabel()})";

                return [$plan->id => $label];
            })
            ->all();
    }

    private static function sessionOptionsForPlan(mixed $treatmentPlanId): array
    {
        if (! is_numeric($treatmentPlanId)) {
            return [];
        }

        $query = TreatmentSession::query()
            ->select(['id', 'performed_at', 'status', 'procedure'])
            ->where('treatment_plan_id', (int) $treatmentPlanId)
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        $authUser = BranchAccess::currentUser();

        if ($authUser instanceof \App\Models\User && ! $authUser->hasRole('Admin')) {
            $branchIds = BranchAccess::accessibleBranchIds($authUser);
            if ($branchIds === []) {
                return [];
            }

            $query->whereHas('treatmentPlan', fn (Builder $planQuery) => $planQuery->whereIn('branch_id', $branchIds));
        }

        return $query
            ->limit(100)
            ->get()
            ->mapWithKeys(function (TreatmentSession $session): array {
                $performedAt = $session->performed_at
                    ? Carbon::parse($session->performed_at)->format('d/m/Y H:i')
                    : 'Chưa có ngày';

                $status = $session->status ?: 'N/A';
                $procedure = filled($session->procedure) ? " - {$session->procedure}" : '';

                return [$session->id => "#{$session->id} · {$performedAt} · {$status}{$procedure}"];
            })
            ->all();
    }

    private static function resolvePlanSubtotal(TreatmentPlan $plan): float
    {
        $sumFinalAmount = (float) $plan->planItems->sum(function ($item): float {
            return (float) ($item->final_amount ?? 0);
        });

        if ($sumFinalAmount > 0) {
            return round($sumFinalAmount, 2);
        }

        $sumByQuantityAndPrice = (float) $plan->planItems->sum(function ($item): float {
            return ((float) ($item->quantity ?? 0)) * ((float) ($item->price ?? 0));
        });

        if ($sumByQuantityAndPrice > 0) {
            return round($sumByQuantityAndPrice, 2);
        }

        if ((float) $plan->total_estimated_cost > 0) {
            return round((float) $plan->total_estimated_cost, 2);
        }

        if ((float) $plan->total_cost > 0) {
            return round((float) $plan->total_cost, 2);
        }

        return 0.0;
    }

    private static function syncTotalAmount(Set $set, Get $get): void
    {
        $set('total_amount', Invoice::calculateTotalAmount(
            self::normalizeAmount($get('subtotal')),
            self::normalizeAmount($get('discount_amount')),
            self::normalizeAmount($get('tax_amount'))
        ));
    }

    private static function normalizeAmount(mixed $value): float
    {
        return max(0, round((float) $value, 2));
    }
}
