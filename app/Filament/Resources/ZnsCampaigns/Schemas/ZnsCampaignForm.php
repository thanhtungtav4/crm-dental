<?php

namespace App\Filament\Resources\ZnsCampaigns\Schemas;

use App\Models\ZnsCampaign;
use App\Support\BranchAccess;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ZnsCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Mã campaign')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('name')
                    ->label('Tên campaign')
                    ->required()
                    ->maxLength(255),

                Select::make('branch_id')
                    ->label('Chi nhánh')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('audience_source')
                    ->label('Nguồn khách hàng')
                    ->options(fn (): array => \App\Support\ClinicRuntimeSettings::customerSourceOptions())
                    ->searchable()
                    ->nullable(),

                TextInput::make('audience_last_visit_before_days')
                    ->label('Lọc chưa tái khám (ngày)')
                    ->numeric()
                    ->minValue(1)
                    ->nullable(),

                TextInput::make('template_key')
                    ->label('Template key')
                    ->maxLength(255),

                TextInput::make('template_id')
                    ->label('Template ID (ZNS provider)')
                    ->maxLength(255),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options(ZnsCampaign::statusOptions())
                    ->default(ZnsCampaign::STATUS_DRAFT)
                    ->required(),

                DateTimePicker::make('scheduled_at')
                    ->label('Thời gian chạy')
                    ->native(false)
                    ->seconds(false)
                    ->nullable(),

                Textarea::make('message_payload')
                    ->label('Message payload (JSON)')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state)
                    ->dehydrateStateUsing(function ($state) {
                        if (is_array($state)) {
                            return $state;
                        }

                        $decoded = json_decode((string) $state, true);

                        return is_array($decoded) ? $decoded : [];
                    })
                    ->helperText('Có thể nhập JSON object cho biến template ZNS.')
                    ->rows(8)
                    ->columnSpanFull(),
            ]);
    }
}
