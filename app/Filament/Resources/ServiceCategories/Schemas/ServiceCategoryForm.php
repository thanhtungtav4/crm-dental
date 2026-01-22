<?php

namespace App\Filament\Resources\ServiceCategories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin danh mục')
                    ->schema([
                        TextInput::make('name')
                            ->label('Tên danh mục')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('code')
                            ->label('Mã danh mục')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->alphaNum()
                            ->dehydrateStateUsing(fn(string $state): string => \Illuminate\Support\Str::upper($state))
                            ->columnSpan(1),
                        Select::make('parent_id')
                            ->label('Danh mục cha')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Để trống nếu đây là danh mục cấp 1')
                            ->columnSpan(1),
                        TextInput::make('icon')
                            ->label('Icon (Heroicon)')
                            ->placeholder('outline-heart')
                            ->helperText('VD: outline-heart, outline-sparkles')
                            ->columnSpan(1),
                        Textarea::make('description')
                            ->label('Mô tả')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Hiển thị')
                    ->schema([
                        Select::make('color')
                            ->label('Màu badge')
                            ->options([
                                'primary' => 'Primary (Xanh dương)',
                                'success' => 'Success (Xanh lá)',
                                'danger' => 'Danger (Đỏ)',
                                'warning' => 'Warning (Vàng)',
                                'info' => 'Info (Xanh nhạt)',
                                'gray' => 'Gray (Xám)',
                                'rose' => 'Rose (Hồng)',
                                'amber' => 'Amber (Cam)',
                                'cyan' => 'Cyan (Xanh cyan)',
                            ])
                            ->required()
                            ->default('gray')
                            ->columnSpan(1),
                        TextInput::make('sort_order')
                            ->label('Thứ tự hiển thị')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1),
                        Toggle::make('active')
                            ->label('Kích hoạt')
                            ->default(true)
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }
}
