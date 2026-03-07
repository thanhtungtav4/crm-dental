<?php

namespace App\Filament\Resources\ZnsCampaigns\RelationManagers;

use App\Models\ZnsCampaignDelivery;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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
                    ->formatStateUsing(fn (mixed $state, ZnsCampaignDelivery $record): string => $record->maskedPhone() ?? '-')
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
                TextColumn::make('provider_status_code')
                    ->label('Mã provider')
                    ->default('-')
                    ->toggleable(),
                TextColumn::make('attempt_count')
                    ->label('Lần thử')
                    ->numeric(),
                TextColumn::make('next_retry_at')
                    ->label('Retry tiếp theo')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
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
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        ZnsCampaignDelivery::STATUS_QUEUED => 'Chờ gửi',
                        ZnsCampaignDelivery::STATUS_SENT => 'Đã gửi',
                        ZnsCampaignDelivery::STATUS_FAILED => 'Lỗi',
                        ZnsCampaignDelivery::STATUS_SKIPPED => 'Bỏ qua',
                    ])
                    ->multiple(),
                SelectFilter::make('provider_status_code')
                    ->label('Mã provider')
                    ->options(fn (): array => $this->providerStatusOptions())
                    ->searchable(),
                Filter::make('retry_due')
                    ->label('Retry tới hạn')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
                        ->whereNotNull('next_retry_at')
                        ->where('next_retry_at', '<=', now())),
                Filter::make('terminal_failure')
                    ->label('Terminal failure')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
                        ->whereNull('next_retry_at')),
                Filter::make('processing')
                    ->label('Đang processing')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('processing_token')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Chưa có delivery phù hợp bộ lọc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * @return array<string, string>
     */
    protected function providerStatusOptions(): array
    {
        return $this->getOwnerRecord()
            ->deliveries()
            ->whereNotNull('provider_status_code')
            ->distinct()
            ->orderBy('provider_status_code')
            ->pluck('provider_status_code', 'provider_status_code')
            ->mapWithKeys(static fn (mixed $value, mixed $key): array => [(string) $key => (string) $value])
            ->all();
    }
}
