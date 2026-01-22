<?php

namespace App\Support;

class GenderBadge
{
    public static function norm(?string $gender): string
    {
        $g = strtolower(trim((string) $gender));
        $map = [
            'm' => 'male',
            'male' => 'male',
            'nam' => 'male',
            'anh' => 'male',
            'f' => 'female',
            'female' => 'female',
            'nu' => 'female',
            'nữ' => 'female',
            'chi' => 'female',
            'other' => 'other',
            'khac' => 'other',
            'khác' => 'other',
            'x' => 'other',
            '' => 'unknown',
        ];
        return $map[$g] ?? $g;
    }

    public static function icon(?string $gender): string
    {
        return match (self::norm($gender)) {
            'male' => 'heroicon-m-user',
            'female' => 'heroicon-m-user-circle',
            'other' => 'heroicon-m-user-group',
            default => 'heroicon-m-question-mark-circle',
        };
    }

    public static function color(?string $gender): string
    {
        return match (self::norm($gender)) {
            // Use Filament standard palette names for reliability
            'male' => 'info',
            'female' => 'success',
            'other' => 'gray',
            default => 'gray',
        };
    }
}
