<?php

namespace App\Filament\Resources\Patients\Relations;

use App\Models\Note;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PatientNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return 'Ghi chú';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            \Filament\Forms\Components\Select::make('type')
                ->label('Loại')
                ->options([
                    'general' => 'Chung',
                    'behavior' => 'Hành vi',
                    'complaint' => 'Phàn nàn',
                    'preference' => 'Sở thích',
                ])->required(),
            \Filament\Forms\Components\Textarea::make('content')
                ->label('Nội dung')
                ->rows(4)
                ->required(),
        ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Người tạo')->toggleable(),
                Tables\Columns\BadgeColumn::make('type')->label('Loại'),
                Tables\Columns\TextColumn::make('content')->label('Nội dung')->limit(80)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('Ngày tạo')->dateTime()->sortable(),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Chưa có ghi chú')
            ->emptyStateDescription('Thêm ghi chú để lưu thông tin chăm sóc bệnh nhân.');
    }
}
