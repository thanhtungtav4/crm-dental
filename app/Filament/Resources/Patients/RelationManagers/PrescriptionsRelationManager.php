<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\PatientAssignmentAuthorizer;
use App\Support\BranchAccess;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
            ->columns(1)
            ->schema([
                Section::make('Thông tin đơn thuốc')
                    ->schema([
                        TextInput::make('prescription_code')
                            ->label('Mã đơn thuốc')
                            ->default(fn () => Prescription::generatePrescriptionCode())
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
                            ->options(fn (): array => $this->doctorOptions())
                            ->searchable()
                            ->preload()
                            ->required(),

                        Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Chi tiết thuốc')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->relationship()
                            ->schema([
                                TextInput::make('medication_name')
                                    ->label('Tên thuốc')
                                    ->required()
                                    ->columnSpan(4),

                                TextInput::make('dosage')
                                    ->label('Liều dùng')
                                    ->placeholder('VD: 500mg')
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->label('Số lượng')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('unit')
                                    ->label('Đơn vị')
                                    ->options(PrescriptionItem::getUnits())
                                    ->default('viên')
                                    ->searchable()
                                    ->columnSpan(2),

                                Select::make('instructions')
                                    ->label('Cách dùng')
                                    ->options(array_combine(
                                        PrescriptionItem::getCommonInstructions(),
                                        PrescriptionItem::getCommonInstructions()
                                    ))
                                    ->searchable()
                                    ->allowHtml()
                                    ->columnSpan(2),

                                TextInput::make('duration')
                                    ->label('Thời gian')
                                    ->placeholder('VD: 7 ngày')
                                    ->columnSpan(2),

                                TextInput::make('notes')
                                    ->label('Ghi chú')
                                    ->columnSpanFull(),
                            ])
                            ->columns(12)
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
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Tạo đơn thuốc')
                    ->modalWidth('7xl')
                    ->mutateFormDataUsing(fn (array $data): array => $this->sanitizePrescriptionData($data))
                    ->modalSubmitActionLabel('Lưu đơn thuốc'),
            ])
            ->actions([
                ViewAction::make()
                    ->label('')
                    ->modalWidth('7xl')
                    ->tooltip('Xem chi tiết'),
                EditAction::make()
                    ->label('')
                    ->mutateFormDataUsing(fn (array $data): array => $this->sanitizePrescriptionData($data))
                    ->modalWidth('7xl')
                    ->tooltip('Sửa'),
                DeleteAction::make()
                    ->label('')
                    ->tooltip('Xóa'),
                Action::make('print')
                    ->label('')
                    ->tooltip('In đơn thuốc')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Prescription $record): string => route('prescriptions.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Chưa có đơn thuốc')
            ->emptyStateDescription('Tạo đơn thuốc mới bằng nút bên trên.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    /**
     * @return array<int, string>
     */
    protected function doctorOptions(): array
    {
        return app(PatientAssignmentAuthorizer::class)->assignableDoctorOptions(
            actor: BranchAccess::currentUser(),
            branchId: $this->resolvePrescriptionBranchId(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizePrescriptionData(array $data): array
    {
        $branchId = $this->resolvePrescriptionBranchId($data);

        if ($branchId !== null) {
            BranchAccess::assertCanAccessBranch(
                branchId: $branchId,
                field: 'doctor_id',
                message: 'Bạn không có quyền thao tác đơn thuốc ở chi nhánh này.',
            );
        }

        $data['branch_id'] = $branchId;
        $data['doctor_id'] = app(PatientAssignmentAuthorizer::class)->assertAssignableDoctorId(
            actor: BranchAccess::currentUser(),
            doctorId: isset($data['doctor_id']) && filled($data['doctor_id']) ? (int) $data['doctor_id'] : null,
            branchId: $branchId,
            field: 'doctor_id',
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolvePrescriptionBranchId(array $data = []): ?int
    {
        if (isset($data['branch_id']) && filled($data['branch_id'])) {
            return (int) $data['branch_id'];
        }

        $patientBranchId = $this->getOwnerRecord()->first_branch_id;

        return $patientBranchId !== null ? (int) $patientBranchId : null;
    }
}
