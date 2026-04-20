<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Forms\Components\ToothSelector;
use App\Models\PatientToothCondition;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Support\ToothChartModalViewState;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table; // Import Schema

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
                'chart' => $this->toothChartModalData(),
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
                        if (empty($state)) {
                            return null;
                        }

                        return \App\Models\PatientToothCondition::whereIn('id', $state)
                            ->with('condition')
                            ->get()
                            ->map(fn ($c) => $c->condition?->name)
                            ->join(', ');
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Thủ thuật')
                    ->description(fn ($record) => $record->name != $record->service?->name ? $record->name : null)
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
                    ->color(fn (string $state): string => match ($state) {
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
                        $this->selectTeethStep(),
                        $this->selectDiagnosisStep(),
                        $this->selectServiceStep(),
                    ])
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutatePlanItemFormData($data)),
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

    /**
     * @return array<int|string, string>
     */
    protected function diagnosisOptionsForSelectedTeeth(Get $get): array
    {
        $selectedTeeth = (array) ($get('tooth_ids') ?? []);

        if ($selectedTeeth === []) {
            return [];
        }

        return $this->getOwnerRecord()->toothConditions()
            ->whereIn('tooth_number', $selectedTeeth)
            ->with('condition')
            ->get()
            ->mapWithKeys(function (PatientToothCondition $item): array {
                return [$item->id => "Răng {$item->tooth_number}: {$item->condition?->name}"];
            })
            ->all();
    }

    protected function selectTeethStep(): Step
    {
        return Step::make('Chọn Răng')
            ->schema([
                ToothSelector::make('tooth_ids')
                    ->label('Sơ đồ răng')
                    ->default([])
                    ->live(),
            ]);
    }

    protected function selectDiagnosisStep(): Step
    {
        return Step::make('Chẩn đoán')
            ->schema([
                Forms\Components\CheckboxList::make('diagnosis_ids')
                    ->label('Chọn chẩn đoán cần điều trị (nếu có)')
                    ->options(fn (Get $get): array => $this->diagnosisOptionsForSelectedTeeth($get))
                    ->columns(2)
                    ->gridDirection('row'),
            ]);
    }

    protected function selectServiceStep(): Step
    {
        return Step::make('Dịch vụ')
            ->schema([
                Forms\Components\Select::make('service_id')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $this->syncServiceDefaults($state, $set)),
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
            ]);
    }

    protected function syncServiceDefaults(mixed $state, Set $set): void
    {
        $service = Service::find($state);

        if (! $service instanceof Service) {
            return;
        }

        $set('price', (int) $service->default_price);
        $set('name', $service->name);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutatePlanItemFormData(array $data): array
    {
        $draftPlan = $this->draftTreatmentPlan();

        $data['treatment_plan_id'] = $draftPlan->id;
        $data['status'] = 'pending';

        if (empty($data['name'])) {
            $data['name'] = $this->serviceFallbackName($data['service_id'] ?? null);
        }

        return $data;
    }

    protected function draftTreatmentPlan(): TreatmentPlan
    {
        /** @var TreatmentPlan|null $plan */
        $plan = $this->getOwnerRecord()
            ->treatmentPlans()
            ->where('status', 'draft')
            ->latest()
            ->first();

        if ($plan instanceof TreatmentPlan) {
            return $plan;
        }

        /** @var TreatmentPlan $createdPlan */
        $createdPlan = $this->getOwnerRecord()->treatmentPlans()->create([
            'status' => 'draft',
            'title' => 'Kế hoạch điều trị ngày '.now()->format('d/m/Y'),
            'created_by' => auth()->id(),
        ]);

        return $createdPlan;
    }

    protected function serviceFallbackName(mixed $serviceId): string
    {
        $service = Service::find($serviceId);

        return $service?->name ?? 'Custom Service';
    }

    /**
     * @return array<string, mixed>
     */
    protected function toothChartModalData(): array
    {
        return app(ToothChartModalViewState::class)->build(
            $this->getOwnerRecord()->toothConditions()->with('condition')->get(),
        );
    }
}
