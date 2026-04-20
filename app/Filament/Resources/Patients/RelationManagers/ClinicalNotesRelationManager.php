<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Forms\Components\ToothChart;
use App\Models\Disease;
use App\Services\PatientAssignmentAuthorizer;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClinicalNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'ClinicalNotes';

    protected static ?string $title = 'Phiếu khám';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                $this->generalExamSection(),
                $this->indicationsSection(),
                $this->diagnosisAndTreatmentSection(),
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
                    ->mutateFormDataUsing(fn (array $data): array => $this->sanitizeClinicalNoteData($data))
                    ->modalWidth('6xl'),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->sanitizeClinicalNoteData($data))
                    ->modalWidth('6xl'),
            ])
            ->emptyStateHeading('Chưa có phiếu khám')
            ->emptyStateDescription('Thêm phiếu khám mới bằng nút bên trên.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    /**
     * @return array<int, string>
     */
    protected function doctorOptionsForBranch(Get $get): array
    {
        return app(PatientAssignmentAuthorizer::class)->assignableDoctorOptions(
            actor: BranchAccess::currentUser(),
            branchId: $this->resolveSelectedBranchId($get),
        );
    }

    protected function generalExamSection(): Section
    {
        return Section::make('KHÁM TỔNG QUÁT')
            ->description('Thông tin bác sĩ và nhận xét chung')
            ->collapsible()
            ->schema([
                Grid::make(4)->schema([
                    Select::make('examining_doctor_id')
                        ->label('Bác sĩ khám')
                        ->options(fn (Get $get): array => $this->doctorOptionsForBranch($get))
                        ->searchable()
                        ->preload()
                        ->helperText($this->doctorSelectHelperText())
                        ->columnSpan(1),

                    Select::make('treating_doctor_id')
                        ->label('Bác sĩ điều trị')
                        ->options(fn (Get $get): array => $this->doctorOptionsForBranch($get))
                        ->searchable()
                        ->preload()
                        ->helperText($this->doctorSelectHelperText())
                        ->columnSpan(1),

                    Select::make('branch_id')
                        ->label('Phòng khám')
                        ->relationship(
                            name: 'branch',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('examining_doctor_id', null);
                            $set('treating_doctor_id', null);
                        })
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
            ]);
    }

    protected function doctorSelectHelperText(): string
    {
        return 'Chỉ hiển thị bác sĩ thuộc chi nhánh đang chọn hoặc chi nhánh gốc của bệnh nhân.';
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function otherDiseaseOptions(): array
    {
        return Disease::active()
            ->with('diseaseGroup')
            ->get()
            ->groupBy('diseaseGroup.name')
            ->map(fn ($diseases) => $diseases->pluck('full_name', 'id')->all())
            ->toArray();
    }

    protected function indicationsSection(): Section
    {
        return Section::make('CHỈ ĐỊNH')
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

                ...$this->buildIndicationImageUploadFields(),

                Select::make('other_diseases')
                    ->label('Chọn bệnh khác')
                    ->multiple()
                    ->options(fn (): array => $this->otherDiseaseOptions())
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => in_array('khac', $get('indications') ?? []))
                    ->columnSpanFull(),
            ]);
    }

    protected function diagnosisAndTreatmentSection(): Section
    {
        return Section::make('CHẨN ĐOÁN VÀ ĐIỀU TRỊ')
            ->description('Sơ đồ răng và chẩn đoán')
            ->collapsible()
            ->schema([
                ToothChart::make('tooth_diagnosis_data')
                    ->label('')
                    ->columnSpanFull(),

                Textarea::make('other_diagnosis')
                    ->label('Chẩn đoán khác')
                    ->placeholder('Nhập chẩn đoán hoặc ghi chú khác...')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeClinicalNoteData(array $data): array
    {
        $branchId = isset($data['branch_id']) && filled($data['branch_id'])
            ? (int) $data['branch_id']
            : ($this->getOwnerRecord()->first_branch_id ? (int) $this->getOwnerRecord()->first_branch_id : null);

        if ($branchId !== null) {
            BranchAccess::assertCanAccessBranch(
                branchId: $branchId,
                field: 'branch_id',
                message: 'Bạn không có quyền ghi phiếu khám ở chi nhánh này.',
            );
        }

        $authorizer = app(PatientAssignmentAuthorizer::class);
        $actor = BranchAccess::currentUser();

        $data['branch_id'] = $branchId;
        $data['examining_doctor_id'] = $authorizer->assertAssignableDoctorId(
            actor: $actor,
            doctorId: isset($data['examining_doctor_id']) && filled($data['examining_doctor_id']) ? (int) $data['examining_doctor_id'] : null,
            branchId: $branchId,
            field: 'examining_doctor_id',
        );
        $data['treating_doctor_id'] = $authorizer->assertAssignableDoctorId(
            actor: $actor,
            doctorId: isset($data['treating_doctor_id']) && filled($data['treating_doctor_id']) ? (int) $data['treating_doctor_id'] : null,
            branchId: $branchId,
            field: 'treating_doctor_id',
        );

        return $data;
    }

    protected function resolveSelectedBranchId(Get $get): ?int
    {
        $branchId = $get('branch_id');

        if (filled($branchId)) {
            return (int) $branchId;
        }

        return $this->getOwnerRecord()->first_branch_id ? (int) $this->getOwnerRecord()->first_branch_id : null;
    }

    /**
     * @return array<int, FileUpload>
     */
    protected function buildIndicationImageUploadFields(): array
    {
        $fields = [];

        foreach (ClinicRuntimeSettings::examIndicationOptions() as $key => $label) {
            $normalizedKey = ClinicRuntimeSettings::normalizeExamIndicationKey((string) $key);

            if ($normalizedKey === '') {
                continue;
            }

            $safeDirectory = preg_replace('/[^a-z0-9_-]/', '_', $normalizedKey) ?: 'other';

            $fields[] = FileUpload::make("indication_images.{$normalizedKey}")
                ->label("Ảnh đính kèm - {$label}")
                ->multiple()
                ->image()
                ->imageEditor()
                ->directory("clinical-notes/{$safeDirectory}")
                ->acceptedFileTypes(['image/*'])
                ->maxSize(10240)
                ->panelLayout('grid')
                ->reorderable()
                ->appendFiles()
                ->helperText('Tải ảnh/X-quang trực tiếp từ máy. Nếu file lớn bị gián đoạn, lưu phiếu rồi tải lại từng ảnh để tránh mất dữ liệu đang nhập.')
                ->visible(fn (Get $get): bool => in_array($normalizedKey, (array) ($get('indications') ?? []), true))
                ->columnSpanFull();
        }

        return $fields;
    }
}
