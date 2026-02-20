<?php

namespace App\Filament\Pages;

class DentalChainReport extends PlaceholderPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Báo cáo chi nhánh';

    protected static string|\UnitEnum|null $navigationGroup = 'Quản lý chi nhánh';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'dental-chain/report/revenue-statistical';

    protected static string $pageTitle = 'Báo cáo chi nhánh';

    protected static ?string $pageDescription = 'Tổng hợp doanh thu và số lượng thủ thuật theo chi nhánh.';

    protected static array $pageBullets = [
        'Mã phòng khám',
        'Tên phòng khám',
        'Tổng số lượng thủ thuật',
        'Tổng doanh thu',
    ];
}
