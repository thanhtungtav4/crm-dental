<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Support\ClinicRuntimeSettings;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClinicalNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'ClinicalNotes';

    protected static ?string $title = 'Phiếu khám';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Section 1: KHÁM TỔNG QUÁT (General Exam) - Collapsible
                Section::make('KHÁM TỔNG QUÁT')
                    ->description('Thông tin bác sĩ và nhận xét chung')
                    ->collapsible()
                    ->schema([
                        Grid::make(4)->schema([
                            Select::make('examining_doctor_id')
                                ->label('Bác sĩ khám')
                                ->relationship('examiningDoctor', 'name')
                                ->searchable()
                                ->preload()
                                ->columnSpan(1),

                            Select::make('treating_doctor_id')
                                ->label('Bác sĩ điều trị')
                                ->relationship('treatingDoctor', 'name')
                                ->searchable()
                                ->preload()
                                ->columnSpan(1),

                            Select::make('branch_id')
                                ->label('Phòng khám')
                                ->relationship('branch', 'name')
                                ->searchable()
                                ->preload()
                                ->columnSpan(1),

                            DatePicker::make('date')
                                ->label('Ngày khám')
                                ->default(now())
                                ->required()
                                ->columnSpan(1),
                        ]),

                        Textarea::make('general_exam_notes')
                            ->label('Nhập khám tổng quát')
                            ->placeholder('Ghi nhận xét khám lâm sàng...')
                            ->rows(4)
                            ->columnSpanFull(),

                        Textarea::make('recommendation_notes')
                            ->label('Nhập kế hoạch điều trị')
                            ->placeholder('Ghi hướng điều trị tổng quát...')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                // Section 2: CHỈ ĐỊNH (Orders) - Collapsible with dynamic image upload
                Section::make('CHỈ ĐỊNH')
                    ->description('Chỉ định cận lâm sàng (Chụp X-quang, Xét nghiệm máu)')
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('indications')
                            ->label('')
                            ->options(fn (): array => ClinicRuntimeSettings::examIndicationOptions())
                            ->columns(5)
                            ->gridDirection('row')
                            ->live()
                            ->columnSpanFull(),

                        // Dynamic image upload for Ảnh (ext) - appears when checkbox is checked
                        FileUpload::make('indication_images.ext')
                            ->label('Ảnh ngoài miệng (ext)')
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->directory('clinical-notes/ext')
                            ->acceptedFileTypes(['image/*'])
                            ->maxSize(10240)
                            ->panelLayout('grid')
                            ->reorderable()
                            ->appendFiles()
                            ->visible(fn (Get $get): bool => in_array('ext', $get('indications') ?? []))
                            ->columnSpanFull(),

                        // Dynamic image upload for Ảnh (int) - appears when checkbox is checked
                        FileUpload::make('indication_images.int')
                            ->label('Ảnh trong miệng (int)')
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->directory('clinical-notes/int')
                            ->acceptedFileTypes(['image/*'])
                            ->maxSize(10240)
                            ->panelLayout('grid')
                            ->reorderable()
                            ->appendFiles()
                            ->visible(fn (Get $get): bool => in_array('int', $get('indications') ?? []))
                            ->columnSpanFull(),

                        // Dynamic disease select - appears when "Khác" checkbox is checked
                        Select::make('other_diseases')
                            ->label('Chọn bệnh khác')
                            ->multiple()
                            ->options(
                                fn () => \App\Models\Disease::active()
                                    ->with('diseaseGroup')
                                    ->get()
                                    ->groupBy('diseaseGroup.name')
                                    ->map(fn ($diseases, $group) => $diseases->pluck('full_name', 'id'))
                                    ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => in_array('khac', $get('indications') ?? []))
                            ->columnSpanFull(),
                    ]),

                // Section 3: CHẨN ĐOÁN VÀ ĐIỀU TRỊ (Diagnosis & Treatment) - Collapsible
                Section::make('CHẨN ĐOÁN VÀ ĐIỀU TRỊ')
                    ->description('Sơ đồ răng và chẩn đoán')
                    ->collapsible()
                    ->schema([
                        ViewField::make('tooth_diagnosis_data')
                            ->view('filament.forms.components.tooth-chart')
                            ->label('')
                            ->columnSpanFull(),

                        Textarea::make('other_diagnosis')
                            ->label('Chẩn đoán khác')
                            ->placeholder('Nhập chẩn đoán hoặc ghi chú khác...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->columns([
                TextColumn::make('date')
                    ->label('Ngày khám')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('examiningDoctor.name')
                    ->label('Bác sĩ khám')
                    ->placeholder('—'),

                TextColumn::make('treatingDoctor.name')
                    ->label('Bác sĩ điều trị')
                    ->placeholder('—'),

                TextColumn::make('indications')
                    ->label('Chỉ định')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state).' chỉ định' : '—'),

                TextColumn::make('general_exam_notes')
                    ->label('Ghi chú')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Thêm phiếu khám')
                    ->icon('heroicon-o-plus')
                    ->modalWidth('6xl'),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth('6xl'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có phiếu khám')
            ->emptyStateDescription('Thêm phiếu khám mới bằng nút bên trên.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
