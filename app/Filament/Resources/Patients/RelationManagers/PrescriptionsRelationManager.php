<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'prescriptions';

    protected static ?string $title = 'Đơn thuốc';

    protected static ?string $modelLabel = 'đơn thuốc';

    protected static ?string $pluralModelLabel = 'đơn thuốc';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Thông tin đơn thuốc')
                    ->schema([
                        TextInput::make('prescription_code')
                            ->label('Mã đơn thuốc')
                            ->default(fn() => Prescription::generatePrescriptionCode())
                            ->disabled()
                            ->dehydrated(true)
                            ->required(),

                        TextInput::make('prescription_name')
                            ->label('Tên đơn thuốc')
                            ->placeholder('VD: Đơn thuốc sau nhổ răng')
                            ->maxLength(255),

                        DatePicker::make('treatment_date')
                            ->label('Ngày điều trị')
                            ->default(now())
                            ->required(),

                        Select::make('doctor_id')
                            ->label('Bác sĩ kê đơn')
                            ->relationship('doctor', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Chi tiết thuốc')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->relationship()
                            ->schema([
                                TextInput::make('medication_name')
                                    ->label('Tên thuốc')
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('dosage')
                                    ->label('Liều dùng')
                                    ->placeholder('VD: 500mg'),

                                TextInput::make('quantity')
                                    ->label('Số lượng')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),

                                Select::make('unit')
                                    ->label('Đơn vị')
                                    ->options(PrescriptionItem::getUnits())
                                    ->default('viên')
                                    ->searchable(),

                                Select::make('instructions')
                                    ->label('Cách dùng')
                                    ->options(array_combine(
                                        PrescriptionItem::getCommonInstructions(),
                                        PrescriptionItem::getCommonInstructions()
                                    ))
                                    ->searchable()
                                    ->allowHtml(),

                                TextInput::make('duration')
                                    ->label('Thời gian')
                                    ->placeholder('VD: 7 ngày'),

                                TextInput::make('notes')
                                    ->label('Ghi chú')
                                    ->columnSpan(2),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Thêm thuốc')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->cloneable(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('prescription_code')
            ->columns([
                TextColumn::make('treatment_date')
                    ->label('Ngày điều trị')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('prescription_code')
                    ->label('Mã đơn thuốc')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('prescription_name')
                    ->label('Tên đơn thuốc')
                    ->limit(30)
                    ->searchable(),

                TextColumn::make('doctor.name')
                    ->label('Bác sĩ kê đơn')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('Số thuốc')
                    ->counts('items')
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Thêm đơn thuốc')
                    ->icon('heroicon-o-plus'),
            ])
            ->actions([
                ViewAction::make()
                    ->label('')
                    ->tooltip('Xem chi tiết'),
                EditAction::make()
                    ->label('')
                    ->tooltip('Sửa'),
                DeleteAction::make()
                    ->label('')
                    ->tooltip('Xóa'),
                Action::make('print')
                    ->label('')
                    ->tooltip('In đơn thuốc')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn(Prescription $record): string => route('prescriptions.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có đơn thuốc')
            ->emptyStateDescription('Tạo đơn thuốc mới bằng nút bên trên.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                CreateAction::make('empty_create')
                    ->label('Thêm đơn thuốc')
                    ->icon('heroicon-o-plus')
                    ->color('primary'),
            ]);
    }
}
