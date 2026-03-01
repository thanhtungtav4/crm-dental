<?php

namespace App\Filament\Resources\PatientWallets\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PatientWalletForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('patient')
                    ->label('Bệnh nhân')
                    ->content(fn ($record) => $record?->patient?->full_name ?? '-'),
                Placeholder::make('branch')
                    ->label('Chi nhánh')
                    ->content(fn ($record) => $record?->branch?->name ?? '-'),
                Placeholder::make('balance')
                    ->label('Số dư ví')
                    ->content(fn ($record) => number_format((float) ($record?->balance ?? 0), 0, ',', '.').'đ'),
                Placeholder::make('deposit')
                    ->label('Tổng nạp cọc')
                    ->content(fn ($record) => number_format((float) ($record?->total_deposit ?? 0), 0, ',', '.').'đ'),
                Placeholder::make('spent')
                    ->label('Tổng đã dùng')
                    ->content(fn ($record) => number_format((float) ($record?->total_spent ?? 0), 0, ',', '.').'đ'),
                Placeholder::make('refunded')
                    ->label('Tổng hoàn ví')
                    ->content(fn ($record) => number_format((float) ($record?->total_refunded ?? 0), 0, ',', '.').'đ'),
                Placeholder::make('wallet_hint')
                    ->label('Ghi chú')
                    ->content(new HtmlString('<span class="text-sm text-gray-600">Ví được cập nhật tự động từ phiếu cọc/hoàn hoặc thanh toán nguồn ví.</span>'))
                    ->columnSpanFull(),
            ]);
    }
}
