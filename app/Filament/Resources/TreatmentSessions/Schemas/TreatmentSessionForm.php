<?php

namespace App\Filament\Resources\TreatmentSessions\Schemas;

use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Services\TreatmentAssignmentAuthorizer;
use App\Support\BranchAccess;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

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
                        $branchId = self::resolveTreatmentPlanBranchId($planId);
                        $authorizer = app(TreatmentAssignmentAuthorizer::class);

                        if (! $planId) {
                            $set('doctor_id', null);
                            $set('assistant_id', null);

                            return;
                        }

                        $plan = TreatmentPlan::query()
                            ->select(['id', 'doctor_id', 'branch_id'])
                            ->find($planId);

                        if ($plan && ! is_numeric($get('doctor_id')) && is_numeric($plan->doctor_id)) {
                            $planDoctorId = (int) $plan->doctor_id;

                            if ($authorizer->isAssignableDoctorId(auth()->user(), $planDoctorId, $branchId)) {
                                $set('doctor_id', $planDoctorId);
                            }
                        }

                        $selectedDoctorId = is_numeric($get('doctor_id')) ? (int) $get('doctor_id') : null;
                        if ($selectedDoctorId !== null && ! $authorizer->isAssignableDoctorId(auth()->user(), $selectedDoctorId, $branchId)) {
                            $set('doctor_id', null);
                        }

                        $selectedAssistantId = is_numeric($get('assistant_id')) ? (int) $get('assistant_id') : null;
                        if ($selectedAssistantId !== null && ! $authorizer->isAssignableStaffId(auth()->user(), $selectedAssistantId, $branchId)) {
                            $set('assistant_id', null);
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
                    ->options(fn (Get $get): array => app(TreatmentAssignmentAuthorizer::class)
                        ->assignableDoctorOptions(
                            actor: auth()->user(),
                            branchId: self::resolveTreatmentPlanBranchId($get('treatment_plan_id')),
                        ))
                    ->label('Bác sĩ')
                    ->searchable()
                    ->preload()
                    ->helperText('Chỉ hiển thị bác sĩ thuộc chi nhánh của kế hoạch đã chọn.')
                    ->nullable(),
                Forms\Components\Select::make('assistant_id')
                    ->options(fn (Get $get): array => app(TreatmentAssignmentAuthorizer::class)
                        ->assignableStaffOptions(
                            actor: auth()->user(),
                            branchId: self::resolveTreatmentPlanBranchId($get('treatment_plan_id')),
                        ))
                    ->label('Trợ thủ')
                    ->searchable()
                    ->preload()
                    ->helperText('Chỉ hiển thị nhân sự thuộc chi nhánh của kế hoạch đã chọn.')
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
                Forms\Components\FileUpload::make('images')
                    ->label('Hình ảnh')
                    ->multiple()
                    ->image()
                    ->imageEditor()
                    ->directory('treatment-sessions/images')
                    ->acceptedFileTypes(['image/*'])
                    ->maxSize(10240)
                    ->panelLayout('grid')
                    ->reorderable()
                    ->appendFiles()
                    ->helperText('Tải ảnh trực tiếp từ máy. Dữ liệu ảnh cũ dạng key:url sẽ tự được chuyển sang danh sách file.')
                    ->afterStateHydrated(function ($state, Set $set): void {
                        $set('images', self::normalizeUploadedImagesState($state));
                    })
                    ->dehydrateStateUsing(fn ($state): array => self::normalizeUploadedImagesState($state))
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
                    ])
                    ->default('scheduled')
                    ->live(),
                Forms\Components\Textarea::make('evidence_override_reason')
                    ->label('Lý do override thiếu evidence')
                    ->rows(2)
                    ->placeholder('Chỉ dùng khi chưa có đủ chứng cứ hình ảnh cho phiên hoàn tất.')
                    ->helperText('Khi chuyển trạng thái Hoàn tất mà thiếu ảnh chứng cứ, phải nhập lý do override và có quyền phù hợp.')
                    ->visible(fn (Get $get): bool => in_array((string) $get('status'), ['done', 'completed'], true))
                    ->columnSpanFull(),
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

    protected static function resolveTreatmentPlanBranchId(mixed $treatmentPlanId): ?int
    {
        if (! is_numeric($treatmentPlanId)) {
            return null;
        }

        $branchId = TreatmentPlan::query()
            ->whereKey((int) $treatmentPlanId)
            ->value('branch_id');

        return is_numeric($branchId) ? (int) $branchId : null;
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

    /**
     * @return array<int, string>
     */
    protected static function normalizeUploadedImagesState(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        return collect(Arr::flatten($state))
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->values()
            ->all();
    }
}
