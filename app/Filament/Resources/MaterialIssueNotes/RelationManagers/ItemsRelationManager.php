<?php

namespace App\Filament\Resources\MaterialIssueNotes\RelationManagers;

use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\MaterialIssueNote;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Vật tư xuất kho';

    public function isReadOnly(): bool
    {
        return $this->ownerRecord->status !== MaterialIssueNote::STATUS_DRAFT;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('material_id')
                    ->label('Vật tư')
                    ->options(fn (): array => $this->materialOptionsForIssueNote())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->helperText('Chi hien vat tu con lo hoat dong, chua het han va thuoc chi nhanh cua phieu.')
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $set('material_batch_id', null);

                        if (! is_numeric($state)) {
                            return;
                        }

                        $material = Material::query()
                            ->select(['id', 'cost_price', 'sale_price'])
                            ->find((int) $state);

                        if ($material) {
                            $set('unit_cost', (float) ($material->cost_price ?? $material->sale_price ?? 0));
                        }
                    }),
                Select::make('material_batch_id')
                    ->label('Lo vat tu')
                    ->options(fn (Get $get): array => $this->batchOptionsForMaterial($get('material_id')))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabled(fn (Get $get): bool => ! is_numeric($get('material_id')))
                    ->helperText('Bat buoc chon lo vat tu de truy vet ton kho va han dung.')
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        if (! is_numeric($state)) {
                            return;
                        }

                        $batch = MaterialBatch::query()
                            ->select(['id', 'material_id', 'purchase_price'])
                            ->find((int) $state);

                        if (! $batch || (int) $batch->material_id !== (int) $get('material_id')) {
                            return;
                        }

                        $set('unit_cost', (float) ($batch->purchase_price ?? 0));
                    }),
                TextInput::make('quantity')
                    ->label('Số lượng')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                TextInput::make('unit_cost')
                    ->label('Đơn giá')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('material.name')
            ->columns([
                TextColumn::make('material.name')
                    ->label('Vật tư')
                    ->searchable(),
                TextColumn::make('materialBatch.batch_number')
                    ->label('Lo')
                    ->default('-'),
                TextColumn::make('quantity')
                    ->label('Số lượng')
                    ->numeric(),
                TextColumn::make('unit_cost')
                    ->label('Đơn giá')
                    ->money('VND', divideBy: 1),
                TextColumn::make('total_cost')
                    ->label('Thành tiền')
                    ->money('VND', divideBy: 1),
                BadgeColumn::make('material.stock_qty')
                    ->label('Tồn kho hiện tại')
                    ->color('gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    protected function materialOptionsForIssueNote(): array
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

        $issueNoteBranchId = is_numeric($this->ownerRecord->branch_id) ? (int) $this->ownerRecord->branch_id : null;

        if ($issueNoteBranchId !== null) {
            $query->where('branch_id', $issueNoteBranchId);
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

                return [(int) $material->id => "{$material->name} (Ton: {$stock}{$unit})"];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function batchOptionsForMaterial(mixed $materialId): array
    {
        if (! is_numeric($materialId)) {
            return [];
        }

        $query = MaterialBatch::query()
            ->where('material_id', (int) $materialId)
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today()->toDateString());
            })
            ->orderBy('expiry_date')
            ->orderBy('batch_number');

        $issueNoteBranchId = is_numeric($this->ownerRecord->branch_id) ? (int) $this->ownerRecord->branch_id : null;

        if ($issueNoteBranchId !== null) {
            $query->whereHas('material', function (Builder $materialQuery) use ($issueNoteBranchId): void {
                $materialQuery->where('branch_id', $issueNoteBranchId);
            });
        }

        return $query
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
}
