<?php

namespace App\Filament\Schemas;

use App\Models\Customer;
use App\Support\ClinicRuntimeSettings;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class SharedSchemas
{
    /**
     * Common fields for Customer/Lead profile.
     * Used in Customer create and Appointment inline create.
     */
    public static function customerProfileFields(): array
    {
        return [
            Forms\Components\TextInput::make('full_name')
                ->label('Họ và tên')
                ->required()
                ->maxLength(255)
                ->placeholder('VD: Nguyễn Văn A')
                ->columnSpan(1),

            Forms\Components\TextInput::make('phone')
                ->label('Số điện thoại')
                ->tel()
                ->maxLength(20)
                ->placeholder('VD: 0901234567')
                ->rule(function ($record): \Closure {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                        if (! is_string($value) || trim($value) === '') {
                            return;
                        }

                        $phoneHash = Customer::phoneSearchHash($value);
                        if ($phoneHash === null) {
                            return;
                        }

                        $exists = Customer::query()
                            ->when($record?->id, fn (EloquentBuilder $query): EloquentBuilder => $query->whereKeyNot((int) $record->id))
                            ->where('phone_search_hash', $phoneHash)
                            ->exists();

                        if ($exists) {
                            $fail('Số điện thoại này đã tồn tại.');
                        }
                    };
                })
                ->columnSpan(1),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255)
                ->placeholder('VD: email@example.com')
                ->columnSpan(1),

            Forms\Components\DatePicker::make('birthday')
                ->label('Ngày sinh')
                ->maxDate(now())
                ->native(false)
                ->displayFormat('d/m/Y')
                ->columnSpan(1),

            Forms\Components\Select::make('gender')
                ->label('Giới tính')
                ->options(fn (): array => ClinicRuntimeSettings::genderOptions())
                ->default('male')
                ->columnSpan(1),

            Forms\Components\Textarea::make('address')
                ->label('Địa chỉ')
                ->rows(2)
                ->columnSpanFull()
                ->placeholder('Nhập địa chỉ chi tiết...'),
        ];
    }
}
