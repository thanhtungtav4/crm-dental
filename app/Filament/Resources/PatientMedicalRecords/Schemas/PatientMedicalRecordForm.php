<?php

namespace App\Filament\Resources\PatientMedicalRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PatientMedicalRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin bệnh nhân')
                    ->schema([
                        Select::make('patient_id')
                            ->label('Bệnh nhân')
                            ->relationship('patient', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Mỗi bệnh nhân chỉ có một hồ sơ y tế')
                            ->columnSpanFull(),
                    ]),

                Section::make('⚠️ Thông tin quan trọng - An toàn bệnh nhân')
                    ->description('Các thông tin dưới đây CỰC KỲ QUAN TRỌNG cho an toàn điều trị')
                    ->schema([
                        TagsInput::make('allergies')
                            ->label('🚨 Dị ứng')
                            ->placeholder('Nhấn Enter sau mỗi loại dị ứng')
                            ->helperText('VD: Penicillin, Lidocaine, Latex, Iodine')
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
                            ->helperText('VD: Tiểu đường, Cao huyết áp, Hen suyễn, Tim mạch')
                            ->columnSpanFull()
                            ->suggestions([
                                'Tiểu đường (Diabetes)',
                                'Cao huyết áp',
                                'Bệnh tim mạch',
                                'Hen suyễn',
                                'Bệnh phổi tắc nghẽn mãn tính (COPD)',
                                'Loãng xương',
                                'Bệnh thận mãn tính',
                                'Bệnh gan',
                            ]),
                        Repeater::make('current_medications')
                            ->label('Thuốc đang sử dụng')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Tên thuốc')
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('dosage')
                                    ->label('Liều lượng')
                                    ->placeholder('VD: 500mg, 2 viên')
                                    ->columnSpan(1),
                                TextInput::make('frequency')
                                    ->label('Tần suất')
                                    ->placeholder('VD: 2 lần/ngày, sáng tối')
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addActionLabel('+ Thêm thuốc')
                            ->collapsed()
                            ->cloneable(),
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
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Thông tin bảo hiểm')
                    ->schema([
                        TextInput::make('insurance_provider')
                            ->label('Công ty bảo hiểm')
                            ->maxLength(255)
                            ->placeholder('VD: Bảo Việt, Prudential, Manulife')
                            ->columnSpan(1),
                        TextInput::make('insurance_number')
                            ->label('Số thẻ bảo hiểm')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                        DatePicker::make('insurance_expiry_date')
                            ->label('Ngày hết hạn')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('Sẽ nhắc nhở khi sắp hết hạn')
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Liên hệ khẩn cấp')
                    ->schema([
                        TextInput::make('emergency_contact_name')
                            ->label('Họ tên người liên hệ')
                            ->maxLength(255)
                            ->placeholder('VD: Nguyễn Văn A')
                            ->columnSpan(1),
                        TextInput::make('emergency_contact_phone')
                            ->label('Số điện thoại')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('VD: 0901234567')
                            ->columnSpan(1),
                        TextInput::make('emergency_contact_relationship')
                            ->label('Quan hệ')
                            ->maxLength(100)
                            ->placeholder('VD: Vợ/chồng, Con, Anh/chị/em')
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Ghi chú bổ sung')
                    ->schema([
                        Textarea::make('additional_notes')
                            ->label('Ghi chú khác')
                            ->rows(4)
                            ->columnSpanFull()
                            ->placeholder('Các thông tin y tế quan trọng khác...'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Thông tin hệ thống')
                    ->schema([
                        Placeholder::make('updated_by_info')
                            ->label('Người cập nhật gần nhất')
                            ->content(fn ($record) => $record?->updatedBy?->name ?? 'Chưa có')
                            ->columnSpan(1),
                        Placeholder::make('updated_at')
                            ->label('Thời gian cập nhật')
                            ->content(fn ($record) => $record?->updated_at?->format('d/m/Y H:i') ?? 'Chưa có')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->visible(fn ($record) => $record !== null),
            ]);
    }
}
