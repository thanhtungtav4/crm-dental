<?php

namespace App\Filament\Resources\TreatmentPlans\Schemas;

use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TreatmentPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Wizard::make([
                    \Filament\Schemas\Components\Wizard\Step::make('Thông tin chung')
                        ->icon('heroicon-m-user')
                        ->schema([
                            Group::make()
                                ->schema([
                                    Forms\Components\Select::make('patient_id')
                                        ->relationship('patient', 'full_name')
                                        ->label('Bệnh nhân')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm(\App\Filament\Resources\Patients\Schemas\PatientForm::getFormSchema())
                                        ->default(request()->query('patient_id'))
                                        ->columnSpan(1),
                                    Forms\Components\Select::make('doctor_id')
                                        ->relationship('doctor', 'name')
                                        ->label('Bác sĩ điều trị')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->columnSpan(1),
                                ])->columns(2),

                            Forms\Components\TextInput::make('title')
                                ->label('Tiêu đề kế hoạch')
                                ->maxLength(255)
                                ->placeholder('VD: Niềng răng chỉnh nha (Giai đoạn 1)...')
                                ->required()
                                ->columnSpanFull(),

                            Group::make()->schema([
                                Forms\Components\Select::make('branch_id')
                                    ->relationship('branch', 'name')
                                    ->label('Chi nhánh')
                                    ->default(fn() => auth()->user()?->branch_id)
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('priority')
                                    ->label('Độ ưu tiên')
                                    ->options([
                                        'low' => 'Thấp',
                                        'normal' => 'Bình thường',
                                        'high' => 'Cao',
                                        'urgent' => 'Khẩn cấp',
                                    ])
                                    ->default('normal')
                                    ->required(),
                            ])->columns(2),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Khám & Chỉ định')
                        ->icon('heroicon-m-clipboard-document-list')
                        ->schema([
                            Section::make('Khám tổng quát')
                                ->schema([
                                    Group::make()->schema([
                                        Forms\Components\TextInput::make('general_exam_data.pulse')
                                            ->label('Mạch (lần/phút)')
                                            ->numeric(),
                                        Forms\Components\TextInput::make('general_exam_data.blood_pressure')
                                            ->label('Huyết áp (mmHg)'),
                                        Forms\Components\TextInput::make('general_exam_data.weight')
                                            ->label('Cân nặng (kg)')
                                            ->numeric(),
                                        Forms\Components\TextInput::make('general_exam_data.height')
                                            ->label('Chiều cao (cm)')
                                            ->numeric(),
                                    ])->columns(4),
                                    Forms\Components\Textarea::make('general_exam_data.medical_history')
                                        ->label('Tiền sử bệnh lý / Ghi chú khám')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),
                            Section::make('Chỉ định cận lâm sàng')
                                ->schema([
                                    Forms\Components\Repeater::make('general_exam_data.indications')
                                        ->label('Danh sách chỉ định')
                                        ->schema([
                                            Forms\Components\Select::make('type')
                                                ->label('Loại chỉ định')
                                                ->options([
                                                    'xray_pano' => 'Chụp X-Quang Panorama',
                                                    'xray_ct' => 'Chụp phim CT Conebeam',
                                                    'blood_test' => 'Xét nghiệm máu',
                                                    'other' => 'Khác',
                                                ])
                                                ->required()
                                                ->columnSpan(1),
                                            Forms\Components\TextInput::make('note')
                                                ->label('Ghi chú / Vị trí')
                                                ->placeholder('VD: Răng 38, Nhổ răng khôn...')
                                                ->columnSpan(2),
                                        ])
                                        ->columns(3)
                                        ->addActionLabel('Thêm chỉ định')
                                        ->defaultItems(0),
                                ]),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Chẩn đoán & Điều trị')
                        ->icon('heroicon-m-sparkles')
                        ->schema([
                            // Visual Aid
                            Section::make('Sơ đồ răng')
                                ->schema([
                                    Forms\Components\ViewField::make('tooth_chart_visual')
                                        ->view('filament.forms.components.tooth-chart')
                                        ->columnSpanFull(),
                                ])
                                ->collapsible(),

                            // The Main Action
                            Section::make('Danh sách hạng mục điều trị')
                                ->schema([
                                    Forms\Components\Repeater::make('planItems')
                                        ->relationship('planItems')
                                        ->hiddenLabel()
                                        ->schema([
                                            Group::make()->schema([
                                                Forms\Components\Select::make('service_id')
                                                    ->relationship('service', 'name')
                                                    ->label('Dịch vụ')
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Set $set) {
                                                        if ($state) {
                                                            $service = \App\Models\Service::find($state);
                                                            if ($service) {
                                                                $set('price', $service->default_price);
                                                                $set('name', $service->name);
                                                            }
                                                        }
                                                    })
                                                    ->columnSpan(4),

                                                \App\Filament\Forms\Components\ToothPicker::make('tooth_number')
                                                    ->label('Răng số')
                                                    ->columnSpan(2)
                                                    ->afterStateHydrated(function ($component, $state) {
                                                        if (is_string($state) && !empty($state)) {
                                                            $component->state(array_map('trim', explode(',', $state)));
                                                        }
                                                    })
                                                    ->dehydrateStateUsing(fn($state) => is_array($state) ? implode(',', $state) : $state),

                                                Forms\Components\TextInput::make('quantity')
                                                    ->label('SL')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->required()
                                                    ->columnSpan(2)
                                                    ->live(),

                                                Forms\Components\TextInput::make('price')
                                                    ->label('Đơn giá')
                                                    ->numeric()
                                                    ->prefix('₫')
                                                    ->required()
                                                    ->columnSpan(4),
                                            ])->columns(12),

                                            //

                                            Forms\Components\Textarea::make('notes')
                                                ->label('Ghi chú chi tiết')
                                                ->rows(1)
                                                ->columnSpanFull(),

                                            Forms\Components\Hidden::make('name'),
                                        ])
                                        ->defaultItems(1)
                                        ->addActionLabel('Thêm dịch vụ / thủ thuật')
                                        ->reorderableWithButtons(),
                                ]),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Hoàn tất')
                        ->icon('heroicon-m-check-circle')
                        ->schema([
                            Section::make('Dự kiến & Trạng thái')
                                ->schema([
                                    Forms\Components\DatePicker::make('expected_start_date')
                                        ->label('Ngày bắt đầu dự kiến')
                                        ->default(now()),
                                    Forms\Components\DatePicker::make('expected_end_date')
                                        ->label('Ngày hoàn thành dự kiến'),
                                    Forms\Components\Select::make('status')
                                        ->label('Trạng thái kế hoạch')
                                        ->options([
                                            'draft' => 'Nháp',
                                            'approved' => 'Đã duyệt',
                                            'in_progress' => 'Đang thực hiện',
                                        ])
                                        ->default('draft')
                                        ->required(),
                                ])->columns(3),

                            Section::make('Hình ảnh hồ sơ')
                                ->schema([
                                    FileUpload::make('before_photo')
                                        ->label('Ảnh trước điều trị')
                                        ->image()
                                        ->directory('treatment-photos/before')
                                        ->columnSpan(1),
                                    FileUpload::make('after_photo')
                                        ->label('Ảnh sau điều trị')
                                        ->image()
                                        ->directory('treatment-photos/after')
                                        ->columnSpan(1),
                                ])->columns(2),
                        ]),
                ])
                    ->columnSpanFull()
                    // Improve UX by allowing skipping steps if needed, or strict validation
                    ->skippable(false)
                    ->persistStepInQueryString('plan-step')
            ]);
    }
}
