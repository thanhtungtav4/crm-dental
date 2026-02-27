<?php

namespace App\Filament\Resources\PlanItems\Schemas;

use App\Models\PatientToothCondition;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\TreatmentPlan;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PlanItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Liên kết điều trị')
                    ->schema([
                        Select::make('treatment_plan_id')
                            ->label('Kế hoạch điều trị')
                            ->relationship('treatmentPlan', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        Placeholder::make('patient_context')
                            ->label('Bệnh nhân')
                            ->content(function (Get $get): string {
                                $planId = $get('treatment_plan_id');
                                if (! is_numeric($planId)) {
                                    return '-';
                                }

                                $plan = TreatmentPlan::query()
                                    ->with('patient:id,full_name,phone')
                                    ->find((int) $planId);

                                if (! $plan?->patient) {
                                    return '-';
                                }

                                $phone = $plan->patient->phone ? " · {$plan->patient->phone}" : '';

                                return "{$plan->patient->full_name}{$phone}";
                            }),

                        Select::make('service_id')
                            ->label('Dịch vụ')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                if (! is_numeric($state)) {
                                    return;
                                }

                                $service = Service::query()->find((int) $state, ['name', 'default_price']);
                                if (! $service) {
                                    return;
                                }

                                if (blank($get('name'))) {
                                    $set('name', $service->name);
                                }

                                if (self::normalizeAmount($get('price')) <= 0) {
                                    $set('price', (float) ($service->default_price ?? 0));
                                }

                                self::syncCalculatedAmounts($set, $get);
                            }),

                        TextInput::make('name')
                            ->label('Tên thủ thuật')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('tooth_number')
                            ->label('Răng số')
                            ->maxLength(255)
                            ->placeholder('Ví dụ: 18,17,16'),

                        Select::make('diagnosis_ids')
                            ->label('Tình trạng răng')
                            ->multiple()
                            ->options(fn (Get $get): array => self::diagnosisOptionsForPlan($get('treatment_plan_id')))
                            ->searchable()
                            ->preload()
                            ->helperText('Có thể chọn nhiều tình trạng để liên kết hạng mục điều trị.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Giá trị & trạng thái')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Số lượng')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live()
                            ->afterStateHydrated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get)),

                        TextInput::make('price')
                            ->label('Đơn giá')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('VNĐ')
                            ->live()
                            ->afterStateHydrated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get)),

                        TextInput::make('discount_percent')
                            ->label('Giảm giá (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%')
                            ->live()
                            ->afterStateHydrated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get)),

                        TextInput::make('discount_amount')
                            ->label('Tiền giảm giá')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('VNĐ')
                            ->live()
                            ->afterStateHydrated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get)),

                        TextInput::make('vat_amount')
                            ->label('Thuế / phụ phí')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('VNĐ')
                            ->live()
                            ->afterStateHydrated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get): mixed => self::syncCalculatedAmounts($set, $get)),

                        TextInput::make('final_amount')
                            ->label('Tổng chi phí')
                            ->numeric()
                            ->required()
                            ->readOnly()
                            ->prefix('VNĐ')
                            ->helperText('Tự tính theo: (S.L * Đơn giá) - Tiền giảm giá + Thuế/phụ phí.'),

                        Select::make('approval_status')
                            ->label('KH đồng ý')
                            ->options(PlanItem::approvalStatusOptions())
                            ->required()
                            ->native(false)
                            ->live(),

                        Textarea::make('approval_decline_reason')
                            ->label('Lý do từ chối')
                            ->rows(2)
                            ->visible(fn (Get $get): bool => $get('approval_status') === PlanItem::APPROVAL_DECLINED)
                            ->required(fn (Get $get): bool => $get('approval_status') === PlanItem::APPROVAL_DECLINED),

                        Select::make('status')
                            ->label('Tình trạng')
                            ->options([
                                PlanItem::STATUS_PENDING => 'Chờ thực hiện',
                                PlanItem::STATUS_IN_PROGRESS => 'Đang thực hiện',
                                PlanItem::STATUS_COMPLETED => 'Hoàn thành',
                                PlanItem::STATUS_CANCELLED => 'Đã hủy',
                            ])
                            ->required()
                            ->native(false),

                        Select::make('priority')
                            ->label('Ưu tiên')
                            ->options([
                                'low' => 'Thấp',
                                'normal' => 'Bình thường',
                                'high' => 'Cao',
                                'urgent' => 'Khẩn cấp',
                            ])
                            ->required()
                            ->native(false)
                            ->default('normal'),

                        TextInput::make('required_visits')
                            ->label('Số lần điều trị dự kiến')
                            ->numeric()
                            ->minValue(1)
                            ->default(1),

                        TextInput::make('completed_visits')
                            ->label('Số lần đã thực hiện')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Ghi chú')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function syncCalculatedAmounts(Set $set, Get $get): void
    {
        $quantity = max(1, (int) self::normalizeAmount($get('quantity')));
        $price = max(0, self::normalizeAmount($get('price')));
        $lineAmount = $quantity * $price;

        $discountPercent = max(0, min(100, self::normalizeAmount($get('discount_percent'))));
        $discountAmount = max(0, self::normalizeAmount($get('discount_amount')));

        if ($discountAmount <= 0 && $discountPercent > 0) {
            $discountAmount = ($discountPercent / 100) * $lineAmount;
        }

        $discountAmount = min($discountAmount, $lineAmount);
        $vatAmount = max(0, self::normalizeAmount($get('vat_amount')));
        $finalAmount = max(0, $lineAmount - $discountAmount + $vatAmount);

        $set('discount_amount', round($discountAmount, 2));
        $set('final_amount', round($finalAmount, 2));
    }

    private static function normalizeAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private static function diagnosisOptionsForPlan(mixed $treatmentPlanId): array
    {
        if (! is_numeric($treatmentPlanId)) {
            return [];
        }

        $plan = TreatmentPlan::query()
            ->select(['id', 'patient_id'])
            ->find((int) $treatmentPlanId);

        if (! $plan) {
            return [];
        }

        return PatientToothCondition::query()
            ->with('condition:id,name')
            ->where('patient_id', $plan->patient_id)
            ->where(function ($query): void {
                $query->whereNull('treatment_status')
                    ->orWhereIn('treatment_status', [
                        PatientToothCondition::STATUS_CURRENT,
                        PatientToothCondition::STATUS_IN_TREATMENT,
                    ]);
            })
            ->orderByRaw('CAST(tooth_number AS UNSIGNED) ASC')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (PatientToothCondition $diagnosis): array {
                $label = trim(sprintf(
                    'Răng %s - %s',
                    (string) ($diagnosis->tooth_number ?: '-'),
                    (string) ($diagnosis->condition?->name ?? $diagnosis->tooth_condition_id)
                ));

                return [$diagnosis->id => $label];
            })
            ->all();
    }
}
