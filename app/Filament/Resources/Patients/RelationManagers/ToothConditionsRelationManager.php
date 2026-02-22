<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\PatientToothCondition;
use App\Models\ToothCondition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class ToothConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'toothConditions';

    protected static ?string $title = 'Sơ đồ răng';

    protected static ?string $modelLabel = 'tình trạng răng';

    protected static ?string $pluralModelLabel = 'tình trạng răng';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Thông tin tình trạng răng')
                    ->schema([
                        Select::make('tooth_number')
                            ->label('Số răng')
                            ->options(function () {
                                $teeth = [];

                                // Adult upper
                                foreach (PatientToothCondition::getAdultTeethUpper() as $tooth) {
                                    $teeth[$tooth] = "Răng $tooth (Hàm trên người lớn)";
                                }

                                // Child upper
                                foreach (PatientToothCondition::getChildTeethUpper() as $tooth) {
                                    $teeth[$tooth] = "Răng $tooth (Răng sữa trên)";
                                }

                                // Child lower
                                foreach (PatientToothCondition::getChildTeethLower() as $tooth) {
                                    $teeth[$tooth] = "Răng $tooth (Răng sữa dưới)";
                                }

                                // Adult lower
                                foreach (PatientToothCondition::getAdultTeethLower() as $tooth) {
                                    $teeth[$tooth] = "Răng $tooth (Hàm dưới người lớn)";
                                }

                                return $teeth;
                            })
                            ->searchable()
                            ->required(),

                        Select::make('tooth_condition_id')
                            ->label('Tình trạng')
                            ->relationship('condition', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('code')
                                    ->label('Mã')
                                    ->required()
                                    ->maxLength(10),
                                TextInput::make('name')
                                    ->label('Tên tình trạng')
                                    ->required()
                                    ->maxLength(100),
                                Select::make('category')
                                    ->label('Phân loại')
                                    ->options(ToothCondition::getCategoryOptions())
                                    ->required(),
                                \Filament\Forms\Components\ColorPicker::make('color')
                                    ->label('Màu hiển thị'),
                            ]),

                        Select::make('treatment_status')
                            ->label('Trạng thái điều trị')
                            ->options(PatientToothCondition::getStatusOptions())
                            ->default(PatientToothCondition::STATUS_CURRENT)
                            ->required(),

                        Select::make('treatment_plan_id')
                            ->label('Kế hoạch điều trị')
                            ->relationship('treatmentPlan', 'title')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        DatePicker::make('diagnosed_at')
                            ->label('Ngày chẩn đoán')
                            ->default(now()),

                        DatePicker::make('completed_at')
                            ->label('Ngày hoàn thành')
                            ->visible(
                                fn(Get $get): bool =>
                                $get('treatment_status') === PatientToothCondition::STATUS_COMPLETED
                            ),

                        Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tooth_number')
            ->columns([
                TextColumn::make('tooth_number')
                    ->label('Số răng')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('condition.code')
                    ->label('Mã')
                    ->searchable()
                    ->badge()
                    ->color(
                        fn(PatientToothCondition $record): string =>
                        $record->condition?->color ?? 'gray'
                    ),

                TextColumn::make('condition.name')
                    ->label('Tình trạng')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('treatment_status')
                    ->label('Trạng thái ĐT')
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string =>
                        PatientToothCondition::getStatusOptions()[$state] ?? $state
                    )
                    ->color(fn(string $state): string => match ($state) {
                        PatientToothCondition::STATUS_CURRENT => 'gray',
                        PatientToothCondition::STATUS_IN_TREATMENT => 'danger',
                        PatientToothCondition::STATUS_COMPLETED => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('diagnosed_at')
                    ->label('Ngày CĐ')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('diagnosedBy.name')
                    ->label('BS chẩn đoán')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->label('Ghi chú')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tooth_number')
            ->filters([
                SelectFilter::make('treatment_status')
                    ->label('Trạng thái')
                    ->options(PatientToothCondition::getStatusOptions()),

                SelectFilter::make('tooth_condition_id')
                    ->label('Tình trạng')
                    ->relationship('condition', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Thêm tình trạng răng')
                    ->icon('heroicon-o-plus'),
                \Filament\Actions\Action::make('openToothChart')
                    ->label('Mở sơ đồ răng')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->modalHeading('Sơ đồ răng')
                    ->modalWidth('7xl')
                    ->modalContent(fn() => view('filament.components.tooth-chart-modal', [
                        'patientId' => $this->ownerRecord->id,
                        'toothConditions' => $this->ownerRecord->toothConditions()->with('condition')->get(),
                    ])),
            ])
            ->actions([
                EditAction::make()
                    ->label('')
                    ->tooltip('Sửa'),
                DeleteAction::make()
                    ->label('')
                    ->tooltip('Xóa'),
                \Filament\Actions\Action::make('startTreatment')
                    ->label('')
                    ->tooltip('Bắt đầu điều trị')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(
                        fn(PatientToothCondition $record): bool =>
                        $record->treatment_status === PatientToothCondition::STATUS_CURRENT
                    )
                    ->requiresConfirmation()
                    ->action(fn(PatientToothCondition $record) => $record->startTreatment()),
                \Filament\Actions\Action::make('completeTreatment')
                    ->label('')
                    ->tooltip('Hoàn thành điều trị')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn(PatientToothCondition $record): bool =>
                        $record->treatment_status === PatientToothCondition::STATUS_IN_TREATMENT
                    )
                    ->requiresConfirmation()
                    ->action(fn(PatientToothCondition $record) => $record->completeTreatment()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có tình trạng răng')
            ->emptyStateDescription('Thêm tình trạng răng bằng nút bên trên hoặc mở sơ đồ răng.')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }
}
