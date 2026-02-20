<?php

namespace App\Filament\Resources\PatientMedicalRecords;

use App\Filament\Resources\PatientMedicalRecords\Pages\CreatePatientMedicalRecord;
use App\Filament\Resources\PatientMedicalRecords\Pages\EditPatientMedicalRecord;
use App\Filament\Resources\PatientMedicalRecords\Pages\ListPatientMedicalRecords;
use App\Filament\Resources\PatientMedicalRecords\Schemas\PatientMedicalRecordForm;
use App\Filament\Resources\PatientMedicalRecords\Tables\PatientMedicalRecordsTable;
use App\Models\PatientMedicalRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PatientMedicalRecordResource extends Resource
{
    protected static ?string $model = PatientMedicalRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Hồ sơ y tế';

    protected static ?string $modelLabel = 'Hồ sơ y tế';

    protected static ?string $pluralModelLabel = 'Hồ sơ y tế';

    protected static ?int $navigationSort = 6;
    
    public static function getNavigationGroup(): ?string
    {
        return 'Hoạt động hàng ngày';
    }

    public static function form(Schema $schema): Schema
    {
        return PatientMedicalRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PatientMedicalRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatientMedicalRecords::route('/'),
            'create' => CreatePatientMedicalRecord::route('/create'),
            'edit' => EditPatientMedicalRecord::route('/{record}/edit'),
        ];
    }
}
