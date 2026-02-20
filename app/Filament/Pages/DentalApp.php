<?php

namespace App\Filament\Pages;

class DentalApp extends PlaceholderPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Ứng dụng mở rộng';

    protected static string|\UnitEnum|null $navigationGroup = 'Ứng dụng mở rộng';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'dental-app';

    protected static string $pageTitle = 'Ứng dụng mở rộng';

    protected static ?string $pageDescription = 'Danh sách ứng dụng tích hợp và cấu hình dịch vụ mở rộng.';

    protected static array $pageBullets = [
        'Dental Google Calendar',
        'Dental ZNS',
        'Dental Chain',
        'Dental SMS Brandname',
        'Dental Call Center',
        'Dental Web Booking',
        'Dentalflow - Đơn thuốc quốc gia',
        'Dental Zalo',
        'DentalFlow - FACEID',
        'Dentalflow - VNPAY',
        'Dentalflow - Hóa đơn điện tử',
        'Dentalflow - Bệnh án điện tử',
    ];
}
