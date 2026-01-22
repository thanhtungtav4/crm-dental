<?php

namespace App\Filament\Resources\Materials\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $title = 'Các lô vật tư';

    protected static ?string $modelLabel = 'lô';

    protected static ?string $pluralModelLabel = 'Các lô';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('batch_number')
                    ->label('Số lô')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('VD: LOT-2024-001')
                    ->unique(ignoreRecord: true),
                DatePicker::make('expiry_date')
                    ->label('🚨 Hạn sử dụng')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(now()),
                DatePicker::make('received_date')
                    ->label('Ngày nhận')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->default(now()),
                TextInput::make('quantity')
                    ->label('Số lượng')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('purchase_price')
                    ->label('Giá nhập')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('VNĐ'),
                Select::make('supplier_id')
                    ->label('Nhà cung cấp')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Đang sử dụng',
                        'expired' => 'Đã hết hạn',
                        'recalled' => 'Thu hồi',
                        'depleted' => 'Đã hết',
                    ])
                    ->default('active')
                    ->required(),
                Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('batch_number')
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Số lô')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),
                TextColumn::make('expiry_date')
                    ->label('🚨 HSD')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => match($record->getExpiryStatusBadge()) {
                        'danger' => 'danger',
                        'warning' => 'warning',
                        'success' => 'success',
                        default => 'gray',
                    })
                    ->description(fn ($record) => $record->getDaysUntilExpiry() . ' ngày'),
                TextColumn::make('quantity')
                    ->label('SL')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($record) => $record->quantity > 0 ? 'success' : 'danger'),
                TextColumn::make('purchase_price')
                    ->label('Giá nhập')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    )
                    ->suffix(' đ')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('received_date')
                    ->label('Ngày nhận')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('supplier.name')
                    ->label('NCC')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Hoạt động',
                        'expired' => 'Hết hạn',
                        'recalled' => 'Thu hồi',
                        'depleted' => 'Hết',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'recalled' => 'warning',
                        'depleted' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                TernaryFilter::make('expiring_soon')
                    ->label('Sắp hết hạn')
                    ->queries(
                        true: fn (Builder $query) => $query->where('expiry_date', '<=', now()->addDays(30))
                            ->where('status', 'active'),
                        false: fn (Builder $query) => $query->where('expiry_date', '>', now()->addDays(30))
                            ->orWhere('status', '!=', 'active'),
                    )
                    ->placeholder('Tất cả'),
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Đang sử dụng',
                        'expired' => 'Đã hết hạn',
                        'recalled' => 'Thu hồi',
                        'depleted' => 'Đã hết',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tạo lô mới')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Sửa')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['updated_by'] = auth()->id();
                        return $data;
                    }),
                DeleteAction::make()
                    ->label('Xóa'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Xóa đã chọn'),
                ]),
            ])
            ->defaultSort('expiry_date', 'asc')
            ->emptyStateHeading('Chưa có lô nào')
            ->emptyStateDescription('Tạo lô mới để theo dõi hạn sử dụng và số lượng vật tư.');
    }
}
