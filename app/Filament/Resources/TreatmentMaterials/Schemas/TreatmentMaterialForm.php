<?php

namespace App\Filament\Resources\TreatmentMaterials\Schemas;

use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\TreatmentSession;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TreatmentMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Forms\Components\Select::make('treatment_session_id')
                    ->label('Phiên điều trị')
                    ->relationship(
                        name: 'session',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (Builder $query): Builder => self::scopeSessionQueryForCurrentUser($query),
                    )
                    ->getOptionLabelFromRecordUsing(fn (TreatmentSession $record): string => self::formatSessionLabel($record))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->default(fn (): ?int => request()->integer('treatment_session_id') ?: null)
                    ->afterStateUpdated(function (Set $set): void {
                        $set('material_id', null);
                        $set('batch_id', null);
                    }),
                Forms\Components\Select::make('material_id')
                    ->label('Vật tư')
                    ->options(fn (Get $get): array => self::materialOptionsForSession($get('treatment_session_id')))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabled(fn (Get $get): bool => ! is_numeric($get('treatment_session_id')))
                    ->helperText('Chi hien vat tu cung chi nhanh va con lo hoat dong de ghi nhan.')
                    ->afterStateUpdated(function (Set $set): void {
                        $set('batch_id', null);
                    }),
                Forms\Components\Select::make('batch_id')
                    ->label('Lô vật tư')
                    ->options(fn (Get $get): array => self::batchOptionsForMaterial($get('material_id')))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (Get $get): bool => ! is_numeric($get('material_id')))
                    ->helperText('Bat buoc chon lo vat tu de truy vet ton kho va han dung.'),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->minValue(1)
                    ->label('Số lượng')
                    ->required(),
                Forms\Components\TextInput::make('cost')
                    ->numeric()
                    ->label('Chi phí'),
                Forms\Components\Select::make('used_by')
                    ->relationship('user', 'name')
                    ->label('Người ghi nhận')
                    ->default(fn (): ?int => auth()->id())
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Được gán tự động theo tài khoản đang thao tác.'),
            ]);
    }

    protected static function scopeSessionQueryForCurrentUser(Builder $query): Builder
    {
        $query->with([
            'treatmentPlan.patient:id,full_name',
            'treatmentPlan.branch:id,name',
        ]);

        $authUser = auth()->user();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = $authUser->accessibleBranchIds();

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('treatmentPlan', function (Builder $treatmentPlanQuery) use ($branchIds): void {
            $treatmentPlanQuery->whereIn('branch_id', $branchIds);
        });
    }

    /**
     * @return array<int, string>
     */
    protected static function materialOptionsForSession(mixed $sessionId): array
    {
        $query = Material::query()
            ->whereHas('batches', function (Builder $batchQuery): void {
                $batchQuery
                    ->where('status', 'active')
                    ->where('quantity', '>', 0)
                    ->where(function (Builder $expiryQuery): void {
                        $expiryQuery
                            ->whereNull('expiry_date')
                            ->orWhereDate('expiry_date', '>=', today()->toDateString());
                    });
            })
            ->orderBy('name');

        $sessionBranchId = null;

        if (is_numeric($sessionId)) {
            $session = TreatmentSession::query()
                ->with('treatmentPlan:id,branch_id')
                ->find((int) $sessionId);

            $sessionBranchId = $session?->treatmentPlan?->branch_id;
        }

        if (is_numeric($sessionBranchId)) {
            $query->where('branch_id', (int) $sessionBranchId);
        } else {
            $authUser = auth()->user();

            if ($authUser instanceof User && ! $authUser->hasRole('Admin')) {
                $branchIds = $authUser->accessibleBranchIds();

                if ($branchIds === []) {
                    return [];
                }

                $query->whereIn('branch_id', $branchIds);
            }
        }

        return $query
            ->limit(300)
            ->get(['id', 'name', 'stock_qty', 'unit'])
            ->mapWithKeys(function (Material $material): array {
                $stock = number_format((int) $material->stock_qty, 0, ',', '.');
                $unit = filled($material->unit) ? " {$material->unit}" : '';

                return [(int) $material->id => "{$material->name} (Tồn: {$stock}{$unit})"];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function batchOptionsForMaterial(mixed $materialId): array
    {
        if (! is_numeric($materialId)) {
            return [];
        }

        return MaterialBatch::query()
            ->where('material_id', (int) $materialId)
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today()->toDateString());
            })
            ->orderBy('expiry_date')
            ->orderBy('batch_number')
            ->limit(200)
            ->get(['id', 'batch_number', 'expiry_date', 'quantity'])
            ->mapWithKeys(function (MaterialBatch $batch): array {
                $expiry = $batch->expiry_date instanceof Carbon
                    ? $batch->expiry_date->format('d/m/Y')
                    : 'Khong ro';
                $quantity = number_format((int) $batch->quantity, 0, ',', '.');

                return [(int) $batch->id => "{$batch->batch_number} · HSD {$expiry} · Ton {$quantity}"];
            })
            ->all();
    }

    protected static function formatSessionLabel(TreatmentSession $session): string
    {
        $session->loadMissing([
            'treatmentPlan.patient:id,full_name',
            'treatmentPlan.branch:id,name',
        ]);

        $patientName = $session->treatmentPlan?->patient?->full_name ?? 'Không rõ bệnh nhân';
        $branchName = $session->treatmentPlan?->branch?->name ?? 'Chưa gán chi nhánh';
        $performedAt = $session->performed_at instanceof Carbon
            ? $session->performed_at->format('d/m/Y H:i')
            : 'Chưa có giờ thực hiện';

        return "#{$session->id} · {$patientName} · {$branchName} · {$performedAt}";
    }
}
