<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FactoryOrderNumberGenerator
{
    public function next(?CarbonInterface $timestamp = null): string
    {
        $resolvedTimestamp = $timestamp ?? now();
        $sequenceDate = $resolvedTimestamp->toDateString();
        $dateFragment = $resolvedTimestamp->format('Ymd');

        return DB::transaction(function () use ($resolvedTimestamp, $sequenceDate, $dateFragment): string {
            DB::table('factory_order_sequences')->insertOrIgnore([
                'sequence_date' => $sequenceDate,
                'last_number' => 0,
                'created_at' => $resolvedTimestamp,
                'updated_at' => $resolvedTimestamp,
            ]);

            $sequence = DB::table('factory_order_sequences')
                ->where('sequence_date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException('Khong the khoi tao sequence cho factory order.');
            }

            $nextNumber = ((int) $sequence->last_number) + 1;

            DB::table('factory_order_sequences')
                ->where('sequence_date', $sequenceDate)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => $resolvedTimestamp,
                ]);

            return sprintf('LAB-%s-%04d', $dateFragment, $nextNumber);
        }, 5);
    }
}
