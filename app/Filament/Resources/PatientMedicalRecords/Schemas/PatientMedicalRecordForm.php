<?php

namespace App\Filament\Resources\PatientMedicalRecords\Schemas;

use App\Models\Disease;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PatientMedicalRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'xl' => 3])
            ->components([
                Group::make()
                    ->columnSpan(['default' => 1, 'xl' => 2])
                    ->schema([
                        Section::make('Liên kết bệnh nhân')
                            ->description('Chọn đúng bệnh nhân trước khi nhập bệnh án để tránh nhầm hồ sơ.')
                            ->schema([
                                Select::make('patient_id')
                                    ->label('Bệnh nhân')
                                    ->relationship(
                                        name: 'patient',
                                        titleAttribute: 'full_name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            $authUser = auth()->user();

                                            if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
                                                return $query;
                                            }

                                            $branchIds = $authUser->accessibleBranchIds();
                                            if ($branchIds === []) {
                                                return $query->whereRaw('1 = 0');
                                            }

                                            return $query->whereIn('first_branch_id', $branchIds);
                                        },
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->default(fn () => request()->integer('patient_id') ?: null)
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Mỗi bệnh nhân chỉ có một hồ sơ y tế.')
                                    ->columnSpanFull(),
                                Placeholder::make('patient_context')
                                    ->label('Tóm tắt bệnh nhân')
                                    ->content(fn (Get $get, ?PatientMedicalRecord $record): string => self::patientContextContent($get, $record))
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Section::make('Yếu tố nguy cơ lâm sàng')
                            ->description('Nhập đầy đủ dị ứng, bệnh nền, thuốc đang dùng trước khi chỉ định điều trị.')
                            ->schema([
                                TagsInput::make('allergies')
                                    ->label('Dị ứng')
                                    ->placeholder('Nhấn Enter sau mỗi loại dị ứng')
                                    ->helperText('Ví dụ: Penicillin, Lidocaine, Latex.')
                                    ->columnSpanFull()
                                    ->suggestions([
                                        'Penicillin',
                                        'Amoxicillin',
                                        'Lidocaine',
                                        'Articaine',
                                        'Latex',
                                        'Iodine',
                                        'Aspirin',
                                        'NSAIDs',
                                    ]),
                                TagsInput::make('chronic_diseases')
                                    ->label('Bệnh lý mãn tính')
                                    ->placeholder('Nhấn Enter sau mỗi bệnh')
                                    ->helperText('Ví dụ: Tiểu đường, Cao huyết áp, Hen suyễn.')
                                    ->columnSpanFull()
                                    ->suggestions(
                                        fn () => Disease::query()
                                            ->active()
                                            ->orderBy('code')
                                            ->limit(80)
                                            ->get()
                                            ->map(fn (Disease $disease) => $disease->full_name)
                                            ->all()
                                    ),
                                Select::make('blood_type')
                                    ->label('Nhóm máu')
                                    ->options([
                                        'A+' => 'A+',
                                        'A-' => 'A-',
                                        'B+' => 'B+',
                                        'B-' => 'B-',
                                        'AB+' => 'AB+',
                                        'AB-' => 'AB-',
                                        'O+' => 'O+',
                                        'O-' => 'O-',
                                        'unknown' => 'Chưa xác định',
                                    ])
                                    ->default('unknown')
                                    ->required(),
                                Repeater::make('current_medications')
                                    ->label('Thuốc đang sử dụng')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Tên thuốc')
                                            ->required(),
                                        TextInput::make('dosage')
                                            ->label('Liều lượng')
                                            ->placeholder('Ví dụ: 500mg, 2 viên'),
                                        TextInput::make('frequency')
                                            ->label('Tần suất')
                                            ->placeholder('Ví dụ: 2 lần/ngày'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->defaultItems(0)
                                    ->addActionLabel('Thêm thuốc')
                                    ->collapsed()
                                    ->cloneable(),
                            ])
                            ->columns(2),

                        Section::make('Thông tin bảo hiểm')
                            ->schema([
                                TextInput::make('insurance_provider')
                                    ->label('Công ty bảo hiểm')
                                    ->maxLength(255)
                                    ->placeholder('Ví dụ: Bảo Việt, Prudential, Manulife'),
                                TextInput::make('insurance_number')
                                    ->label('Số thẻ bảo hiểm')
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),
                                DatePicker::make('insurance_expiry_date')
                                    ->label('Ngày hết hạn bảo hiểm')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->helperText('Hệ thống sẽ dùng mốc này để nhắc hết hạn.'),
                            ])
                            ->columns(3)
                            ->collapsible(),

                        Section::make('Liên hệ khẩn cấp')
                            ->schema([
                                TextInput::make('emergency_contact_name')
                                    ->label('Họ tên')
                                    ->maxLength(255)
                                    ->placeholder('Ví dụ: Nguyễn Văn A'),
                                TextInput::make('emergency_contact_phone')
                                    ->label('Số điện thoại')
                                    ->tel()
                                    ->maxLength(20)
                                    ->placeholder('Ví dụ: 0901234567'),
                                TextInput::make('emergency_contact_email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->placeholder('Ví dụ: contact@example.com'),
                                TextInput::make('emergency_contact_relationship')
                                    ->label('Quan hệ')
                                    ->maxLength(100)
                                    ->placeholder('Ví dụ: Vợ/chồng, Con, Anh/chị/em'),
                            ])
                            ->columns(2)
                            ->collapsible(),

                        Section::make('Ghi chú lâm sàng bổ sung')
                            ->schema([
                                Textarea::make('additional_notes')
                                    ->label('Nội dung ghi chú')
                                    ->rows(5)
                                    ->columnSpanFull()
                                    ->placeholder('Ghi lại lưu ý quan trọng phục vụ điều trị và theo dõi...'),
                            ])
                            ->collapsible(),
                    ]),

                Group::make()
                    ->columnSpan(['default' => 1, 'xl' => 1])
                    ->schema([
                        Section::make('Tóm tắt hồ sơ')
                            ->schema([
                                Placeholder::make('clinical_risk_summary')
                                    ->label('Tổng quan nguy cơ')
                                    ->content(fn (Get $get, ?PatientMedicalRecord $record): string => self::clinicalRiskSummary($get, $record)),
                                Placeholder::make('emergency_summary')
                                    ->label('Liên hệ khẩn cấp')
                                    ->content(fn (Get $get, ?PatientMedicalRecord $record): string => self::emergencySummary($get, $record)),
                            ]),

                        Section::make('Checklist an toàn trước thủ thuật')
                            ->schema([
                                Placeholder::make('safety_checklist')
                                    ->hiddenLabel()
                                    ->content('1) Kiểm tra dị ứng trước khi kê thuốc hoặc gây tê. 2) Đối chiếu thuốc đang dùng để tránh tương tác. 3) Xác nhận liên hệ khẩn cấp còn hiệu lực.'),
                            ]),

                        Section::make('Thông tin hệ thống')
                            ->schema([
                                Placeholder::make('updated_by_info')
                                    ->label('Người cập nhật gần nhất')
                                    ->content(fn (?PatientMedicalRecord $record): string => $record?->updatedBy?->name ?? 'Chưa có'),
                                Placeholder::make('updated_at')
                                    ->label('Thời gian cập nhật')
                                    ->content(fn (?PatientMedicalRecord $record): string => $record?->updated_at?->format('d/m/Y H:i') ?? 'Chưa có'),
                            ])
                            ->collapsed()
                            ->visible(fn (?PatientMedicalRecord $record): bool => $record !== null),
                    ]),
            ]);
    }

    private static function patientContextContent(Get $get, ?PatientMedicalRecord $record): string
    {
        $patientId = $get('patient_id');

        if (! is_numeric($patientId) && $record?->patient_id) {
            $patientId = $record->patient_id;
        }

        $patient = self::resolvePatient($patientId);

        if (! $patient) {
            return 'Chưa chọn bệnh nhân.';
        }

        $ageText = 'Chưa có ngày sinh';

        if ($patient->birthday) {
            $ageText = $patient->birthday->isFuture()
                ? 'Ngày sinh chưa hợp lệ'
                : now()->diffInYears($patient->birthday).' tuổi';
        }

        $phone = filled($patient->phone) ? (string) $patient->phone : 'Chưa có số điện thoại';
        $branchName = $patient->branch?->name ?? 'Chưa có chi nhánh';

        return "{$patient->full_name} ({$patient->patient_code}) · {$phone} · {$ageText} · {$branchName}";
    }

    private static function clinicalRiskSummary(Get $get, ?PatientMedicalRecord $record): string
    {
        $allergies = self::normalizeListValue($get('allergies') ?? $record?->allergies);
        $chronicDiseases = self::normalizeListValue($get('chronic_diseases') ?? $record?->chronic_diseases);
        $currentMedications = self::normalizeListValue($get('current_medications') ?? $record?->current_medications);

        $insuranceProvider = (string) ($get('insurance_provider') ?? $record?->insurance_provider ?? '');
        $insuranceExpiry = self::normalizeDate($get('insurance_expiry_date') ?? $record?->insurance_expiry_date);

        $insuranceText = 'Không có bảo hiểm';

        if (filled($insuranceProvider)) {
            if ($insuranceExpiry && $insuranceExpiry->isPast()) {
                $insuranceText = 'Bảo hiểm đã hết hạn';
            } elseif ($insuranceExpiry) {
                $insuranceText = 'Bảo hiểm còn hiệu lực đến '.$insuranceExpiry->format('d/m/Y');
            } else {
                $insuranceText = 'Có bảo hiểm, chưa có ngày hết hạn';
            }
        }

        return sprintf(
            'Dị ứng: %d · Bệnh nền: %d · Thuốc đang dùng: %d · %s',
            count($allergies),
            count($chronicDiseases),
            count($currentMedications),
            $insuranceText
        );
    }

    private static function emergencySummary(Get $get, ?PatientMedicalRecord $record): string
    {
        $name = trim((string) ($get('emergency_contact_name') ?? $record?->emergency_contact_name ?? ''));
        $phone = trim((string) ($get('emergency_contact_phone') ?? $record?->emergency_contact_phone ?? ''));
        $relationship = trim((string) ($get('emergency_contact_relationship') ?? $record?->emergency_contact_relationship ?? ''));

        if ($name === '' && $phone === '') {
            return 'Chưa có thông tin liên hệ khẩn cấp.';
        }

        $summary = $name !== '' ? $name : 'Chưa có tên';
        $summary .= $phone !== '' ? " · {$phone}" : '';
        $summary .= $relationship !== '' ? " · {$relationship}" : '';

        return $summary;
    }

    private static function resolvePatient(mixed $patientId): ?Patient
    {
        if (! is_numeric($patientId)) {
            return null;
        }

        return Patient::query()
            ->with('branch:id,name')
            ->find((int) $patientId, [
                'id',
                'patient_code',
                'full_name',
                'phone',
                'birthday',
                'first_branch_id',
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function normalizeListValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => filled($item)));
    }

    private static function normalizeDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
