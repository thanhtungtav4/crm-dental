<?php

namespace App\Filament\Resources\TreatmentMaterials\Schemas;

use App\Models\Material;
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
                    ->default(fn () => request()->integer('treatment_session_id') ?: null)
                    ->afterStateUpdated(function (Set $set): void {
                        $set('material_id', null);
                    }),
                Forms\Components\Select::make('material_id')
                    ->label('Vật tư')
                    ->options(fn (Get $get): array => self::materialOptionsForSession($get('treatment_session_id')))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (Get $get): bool => ! is_numeric($get('treatment_session_id')))
                    ->helperText('Danh sách vật tư tự lọc theo chi nhánh của phiên điều trị đã chọn.'),
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
                    ->label('Người dùng')
                    ->searchable()
                    ->preload()
                    ->default(fn () => auth()->id()),
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
        $query = Material::query()->orderBy('name');

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
