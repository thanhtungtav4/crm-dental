<?php

namespace App\Filament\Resources\MaterialIssueNotes\Schemas;

use App\Models\MaterialIssueNote;
use App\Support\BranchAccess;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MaterialIssueNoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('patient_id')
                    ->label('Bệnh nhân')
                    ->relationship(
                        name: 'patient',
                        titleAttribute: 'full_name',
                        modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeQueryByAccessibleBranches($query, 'first_branch_id'),
                    )
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => request()->integer('patient_id') ?: null)
                    ->nullable(),

                Select::make('branch_id')
                    ->label('Chi nhánh')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn (): ?int => request()->integer('branch_id') ?: BranchAccess::defaultBranchIdForCurrentUser()),

                TextInput::make('note_no')
                    ->label('Mã phiếu xuất')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options(MaterialIssueNote::statusOptions())
                    ->default(MaterialIssueNote::STATUS_DRAFT)
                    ->required(),

                DateTimePicker::make('issued_at')
                    ->label('Ngày xuất')
                    ->native(false)
                    ->seconds(false)
                    ->default(now()),

                TextInput::make('reason')
                    ->label('Lý do xuất')
                    ->maxLength(255),

                Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
