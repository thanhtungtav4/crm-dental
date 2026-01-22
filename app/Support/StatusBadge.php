<?php

namespace App\Support;

class StatusBadge
{
    /**
     * Normalize a status string to a consistent key.
     */
    protected static function norm(?string $status): string
    {
        $s = strtolower(trim((string) $status));
        return match ($s) {
            'canceled' => 'cancelled',
            'in progress' => 'in_progress',
            default => $s,
        };
    }

    /**
     * Return a Filament icon name for a given status.
     */
    public static function icon(?string $status): string
    {
        return match (self::norm($status)) {
            // Positive / Done
            'delivered', 'completed', 'paid', 'approved', 'confirmed', 'success' => 'heroicon-o-check-badge',

            // Shipping / Transit
            'shipped', 'shipping', 'dispatch' => 'heroicon-o-truck',

            // New / Created
            'new', 'created', 'lead' => 'heroicon-o-sparkles',

            // Processing / Pending
            'processing', 'pending', 'scheduled', 'in_progress', 'partial' => 'heroicon-o-arrow-path',

            // Cancelled / Failed
            'cancelled', 'failed', 'lost' => 'heroicon-o-x-circle',

            // Default/info
            default => 'heroicon-o-information-circle',
        };
    }

    /**
     * Return a Filament color name for a given status.
     */
    public static function color(?string $status): string
    {
        return match (self::norm($status)) {
            // Positive / Done
            'delivered', 'completed', 'paid', 'approved', 'confirmed', 'success', 'converted' => 'success',

            // Shipping / Transit
            'shipped', 'shipping', 'dispatch' => 'gray',

            // New / Info
            'new', 'created', 'lead' => 'info',

            // Processing / Pending / Scheduled
            'processing', 'pending', 'scheduled', 'in_progress', 'partial', 'contacted', 'draft' => 'warning',

            // Cancelled / Lost / Unpaid
            'cancelled', 'failed', 'lost', 'unpaid' => 'danger',

            // Default
            default => 'gray',
        };
    }

    public static function getColors(): array
    {
        return [
            'success' => ['delivered', 'completed', 'paid', 'approved', 'confirmed', 'success', 'converted'],
            'gray' => ['shipped', 'shipping', 'dispatch'],
            'info' => ['new', 'created', 'lead'],
            'warning' => ['processing', 'pending', 'scheduled', 'in_progress', 'partial', 'contacted', 'draft'],
            'danger' => ['cancelled', 'failed', 'lost', 'unpaid'],
        ];
    }

    public static function getIcons(): array
    {
        return [
            'heroicon-o-check-badge' => ['delivered', 'completed', 'paid', 'approved', 'confirmed', 'success'],
            'heroicon-o-truck' => ['shipped', 'shipping', 'dispatch'],
            'heroicon-o-sparkles' => ['new', 'created', 'lead'],
            'heroicon-o-arrow-path' => ['processing', 'pending', 'scheduled', 'in_progress', 'partial'],
            'heroicon-o-x-circle' => ['cancelled', 'failed', 'lost'],
            'heroicon-o-information-circle' => ['contacted', 'draft'],
        ];
    }
}
