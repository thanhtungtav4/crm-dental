<?php

namespace App\Filament\Pages;

class ZaloZns extends PlaceholderPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Zalo ZNS';

    protected static string|\UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'zalo-zns';

    protected static string $pageTitle = 'Zalo ZNS';

    protected static ?string $pageDescription = 'Quản lý mẫu tin và chiến dịch ZNS cho các luồng CSKH.';

    protected static array $pageBullets = [
        'Mẫu tin',
        'Chiến dịch',
    ];
}
