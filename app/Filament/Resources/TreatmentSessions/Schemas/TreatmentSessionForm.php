<?php

namespace App\Filament\Resources\TreatmentSessions\Schemas;

use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Support\BranchAccess;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TreatmentSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('treatment_plan_id')
                    ->relationship(
                        name: 'treatmentPlan',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query): Builder => self::scopeTreatmentPlanQueryForCurrentUser($query),
                    )
                    ->getOptionLabelFromRecordUsing(fn (TreatmentPlan $record): string => self::formatTreatmentPlanLabel($record))
                    ->label('Kế hoạch')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => self::resolveDefaultTreatmentPlanId())
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                        $planId = is_numeric($state) ? (int) $state : null;
                        $set('plan_item_id', null);

                        if (! $planId) {
                            return;
                        }

                        $plan = TreatmentPlan::query()
                            ->select(['id', 'doctor_id'])
                            ->find($planId);

                        if ($plan && ! is_numeric($get('doctor_id'))) {
                            $set('doctor_id', $plan->doctor_id);
                        }
                    }),
                Forms\Components\Select::make('plan_item_id')
                    ->label('Hạng mục kế hoạch')
                    ->options(fn (Get $get): array => self::planItemOptionsForPlan($get('treatment_plan_id')))
                    ->searchable()
                    ->preload()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->disabled(fn (Get $get): bool => ! is_numeric($get('treatment_plan_id')))
                    ->helperText('Chỉ hiển thị hạng mục thuộc kế hoạch đã chọn.')
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                        if (! is_numeric($state)) {
                            return;
                        }

                        $planItem = PlanItem::query()
                            ->select(['id', 'name'])
                            ->find((int) $state);

                        if ($planItem && blank($get('procedure'))) {
                            $set('procedure', $planItem->name);
                        }
                    }),
                Forms\Components\Select::make('doctor_id')
                    ->relationship('doctor', 'name')
                    ->label('Bác sĩ')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\Select::make('assistant_id')
                    ->relationship('assistant', 'name')
                    ->label('Trợ thủ')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\DateTimePicker::make('performed_at')
                    ->label('Thời gian thực hiện')
                    ->nullable(),
                Forms\Components\Textarea::make('diagnosis')
                    ->label('Chẩn đoán')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('procedure')
                    ->label('Quy trình')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('images')
                    ->label('Hình ảnh (key:url)')
                    ->columnSpanFull()
                    ->nullable(),
                Forms\Components\Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'scheduled' => 'Đã hẹn',
                        'done' => 'Hoàn tất',
                        'follow_up' => 'Tái khám',
                    ])->default('scheduled'),
            ]);
    }

    protected static function scopeTreatmentPlanQueryForCurrentUser(Builder $query): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(
            query: $query->with(['patient:id,full_name,patient_code', 'branch:id,name']),
            column: 'branch_id',
        );
    }

    protected static function resolveDefaultTreatmentPlanId(): ?int
    {
        $planIdFromRequest = request()->integer('treatment_plan_id');
        if ($planIdFromRequest) {
            $isAccessible = self::scopeTreatmentPlanQueryForCurrentUser(TreatmentPlan::query())
                ->whereKey($planIdFromRequest)
                ->exists();

            return $isAccessible ? $planIdFromRequest : null;
        }

        $patientIdFromRequest = request()->integer('patient_id');
        if (! $patientIdFromRequest) {
            return null;
        }

        $planId = self::scopeTreatmentPlanQueryForCurrentUser(TreatmentPlan::query())
            ->where('patient_id', $patientIdFromRequest)
            ->latest('id')
            ->value('id');

        return is_numeric($planId) ? (int) $planId : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function planItemOptionsForPlan(mixed $treatmentPlanId): array
    {
        if (! is_numeric($treatmentPlanId)) {
            return [];
        }

        return PlanItem::query()
            ->where('treatment_plan_id', (int) $treatmentPlanId)
            ->orderBy('id')
            ->get(['id', 'name', 'tooth_number', 'final_amount', 'price'])
            ->mapWithKeys(function (PlanItem $item): array {
                $tooth = filled($item->tooth_number) ? " · Răng {$item->tooth_number}" : '';
                $amountValue = (float) ($item->final_amount ?? $item->price ?? 0);
                $amount = number_format($amountValue, 0, ',', '.');

                return [
                    (int) $item->id => "{$item->name}{$tooth} · {$amount}đ",
                ];
            })
            ->all();
    }

    protected static function formatTreatmentPlanLabel(TreatmentPlan $plan): string
    {
        $plan->loadMissing(['patient:id,full_name,patient_code', 'branch:id,name']);

        $patientName = $plan->patient?->full_name ?? 'Không rõ bệnh nhân';
        $patientCode = filled($plan->patient?->patient_code) ? " ({$plan->patient->patient_code})" : '';
        $branchName = $plan->branch?->name ?? 'Chưa gán chi nhánh';

        return "{$plan->title} · {$patientName}{$patientCode} · {$branchName}";
    }
}
