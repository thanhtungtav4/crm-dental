<?php

namespace App\Filament\Resources\TreatmentSessions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TreatmentSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('treatment_plan_id')
                    ->relationship('treatmentPlan', 'title')
                    ->label('Kế hoạch')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('doctor_id')
                    ->relationship('doctor', 'name')
                    ->label('Bác sĩ')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\DateTimePicker::make('performed_at')
                    ->label('Thời gian thực hiện')
                    ->nullable(),
                Forms\Components\Textarea::make('diagnosis')
                    ->label('Chẩn đoán')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('procedure')
                    ->label('Quy trình')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('images')
                    ->label('Hình ảnh (key:url)')
                    ->columnSpanFull()
                    ->nullable(),
                Forms\Components\Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'scheduled' => 'Đã hẹn',
                        'done' => 'Hoàn tất',
                        'follow_up' => 'Tái khám',
                    ])->default('scheduled'),
            ]);
    }
}
