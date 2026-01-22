<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClinicalNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'ClinicalNotes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('Thông tin khám')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('date')
                            ->label('Ngày khám')
                            ->default(now())
                            ->required(),
                        \Filament\Forms\Components\Select::make('doctor_id')
                            ->label('Bác sĩ')
                            ->relationship('doctor', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Sơ đồ răng (Odontogram)')
                    ->schema([
                        \Filament\Forms\Components\ViewField::make('tooth_chart')
                            ->view('filament.forms.components.tooth-chart')
                            ->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Chẩn đoán & Chỉ định')
                    ->schema([
                        \Filament\Forms\Components\CheckboxList::make('indications')
                            ->label('Chỉ định cận lâm sàng')
                            ->options([
                                'ceph' => 'Cephalometric',
                                'panorama' => 'Panorama',
                                '3d' => 'CT Conebeam 3D',
                                'blood_test' => 'Xét nghiệm máu',
                            ])
                            ->columns(2)
                            ->columnSpan(1),

                        \Filament\Forms\Components\Textarea::make('diagnoses') // Temporary simple field
                            ->label('Chẩn đoán sơ bộ')
                            ->rows(4)
                            ->columnSpan(1),

                        \Filament\Forms\Components\Textarea::make('examination_note')
                            ->label('Ghi chú khám tổng quát')
                            ->rows(3)
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Textarea::make('treatment_plan_note')
                            ->label('Ghi chú kế hoạch điều trị')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('examination_note')
            ->columns([
                TextColumn::make('date')
                    ->label('Ngày khám')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('doctor.name')
                    ->label('Bác sĩ'),
                TextColumn::make('examination_note')
                    ->label('Ghi chú')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->label('Thêm phiếu khám'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
