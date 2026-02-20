<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use BackedEnum;
use Filament\Schemas\Schema; // Import Schema

class DiagnosisAndTreatmentRelationManager extends RelationManager
{
    protected static string $relationship = 'planItems';

    protected static ?string $title = 'Khám & Điều trị';

    protected static string|BackedEnum|null $icon = 'heroicon-o-sparkles';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema // Check signature
    {
        return $schema
            ->components([ // RelationManager default form usually uses components() or schema()? ClinicalNotes uses schema(), generated one used components(). Let's try components() as per generated file earlier, or schema(). Wait, ClinicalNotes uses schema(). Let's check generated file again. Step 467: `public function form(Schema $schema): Schema { return $schema->components([...]); }`. Okay, components() it is.
                Forms\Components\Select::make('service_id')
                    ->relationship('service', 'name')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->header(view('filament.components.tooth-chart-modal', [
                'toothConditions' => $this->getOwnerRecord()->toothConditions,
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('tooth_ids')
                    ->label('Răng')
                    ->badge()
                    ->separator(',')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('diagnosis_ids')
                    ->label('Chẩn đoán')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(function ($state, $record) {
                        if (empty($state))
                            return null;
                        return \App\Models\PatientToothCondition::whereIn('id', $state)
                            ->with('condition')
                            ->get()
                            ->map(fn($c) => $c->condition?->name)
                            ->join(', ');
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Thủ thuật')
                    ->description(fn($record) => $record->name != $record->service?->name ? $record->name : null)
                    ->weight('medium')
                    ->wrap(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Đơn giá')
                    ->money('VND', locale: 'vi'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('SL')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Giảm')
                    ->money('VND', locale: 'vi')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('final_amount')
                    ->label('Thành tiền')
                    ->money('VND', locale: 'vi')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Chờ',
                        'in_progress' => 'Đang làm',
                        'completed' => 'Hoàn thành',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Thêm chỉ định')
                    ->modalHeading('Thêm chỉ định điều trị')
                    ->modalWidth('4xl')
                    ->steps([
                        // Step 1: Select Teeth
                        Forms\Components\Wizard\Step::make('Chọn Răng')
                            ->schema([
                                Forms\Components\ViewField::make('tooth_ids')
                                    ->view('filament.forms.components.tooth-selector')
                                    ->label('Sơ đồ răng')
                                    ->default([])
                                    ->live(),
                            ]),

                        // Step 2: Select Diagnosis
                        Forms\Components\Wizard\Step::make('Chẩn đoán')
                            ->schema([
                                Forms\Components\CheckboxList::make('diagnosis_ids')
                                    ->label('Chọn chẩn đoán cần điều trị (nếu có)')
                                    ->options(function (Forms\Get $get, RelationManager $livewire) {
                                        $selectedTeeth = $get('tooth_ids') ?? [];
                                        if (empty($selectedTeeth))
                                            return [];

                                        return $livewire->getOwnerRecord()->toothConditions()
                                            ->whereIn('tooth_number', $selectedTeeth)
                                            ->with('condition')
                                            ->get()
                                            ->mapWithKeys(function ($item) {
                                                return [$item->id => "Răng {$item->tooth_number}: {$item->condition->name}"];
                                            });
                                    })
                                    ->columns(2)
                                    ->gridDirection('row'),
                            ]),

                        // Step 3: Select Service
                        Forms\Components\Wizard\Step::make('Dịch vụ')
                            ->schema([
                                Forms\Components\Select::make('service_id')
                                    ->relationship('service', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $service = \App\Models\Service::find($state);
                                        if ($service) {
                                            $set('price', (int) $service->default_price);
                                            $set('name', $service->name);
                                        }
                                    }),
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₫'),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),
                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Giảm giá (VNĐ)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('₫'),
                            ]),
                    ])
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        // Auto link to current active treatment plan or create new draft
                        // Find a draft plan for this patient
                        $patient = $livewire->getOwnerRecord();
                        $plan = $patient->treatmentPlans()->where('status', 'draft')->latest()->first();

                        if (!$plan) {
                            $plan = $patient->treatmentPlans()->create([
                                'status' => 'draft',
                                'title' => 'Kế hoạch điều trị ngày ' . now()->format('d/m/Y'),
                                'created_by' => auth()->id(),
                            ]);
                        }

                        $data['treatment_plan_id'] = $plan->id;
                        $data['status'] = 'pending';

                        // Set service name fallback
                        if (empty($data['name'])) {
                            $service = \App\Models\Service::find($data['service_id']);
                            $data['name'] = $service?->name ?? 'Custom Service';
                        }

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có điều trị nào')
            ->defaultSort('created_at', 'desc');
    }
}
