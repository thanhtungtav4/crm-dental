<?php

namespace App\Filament\Resources\ZnsCampaigns\RelationManagers;

use App\Models\ZnsCampaignDelivery;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Delivery log';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('phone')
            ->columns([
                TextColumn::make('phone')
                    ->label('Số điện thoại')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $phoneHash = ZnsCampaignDelivery::phoneSearchHash($search);

                        if ($phoneHash === null) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->where('phone_search_hash', $phoneHash);
                    }),
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->default('-'),
                BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->colors([
                        'gray' => 'queued',
                        'success' => 'sent',
                        'danger' => 'failed',
                        'warning' => 'skipped',
                    ]),
                TextColumn::make('provider_message_id')
                    ->label('Provider message id')
                    ->default('-')
                    ->toggleable(),
                TextColumn::make('error_message')
                    ->label('Lỗi')
                    ->default('-')
                    ->wrap(),
                TextColumn::make('sent_at')
                    ->label('Thời gian gửi')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
