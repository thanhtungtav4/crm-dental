<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('appointments')
            ->whereIn('status', ['pending', 'booked'])
            ->update(['status' => 'scheduled']);

        DB::table('appointments')
            ->whereIn('status', ['done', 'finished'])
            ->update(['status' => 'completed']);

        DB::table('appointments')
            ->where('status', 'canceled')
            ->update(['status' => 'cancelled']);

        DB::table('appointments')
            ->where('status', 'arrived')
            ->update(['status' => 'confirmed']);

        DB::table('appointments')
            ->where('status', 'examining')
            ->update(['status' => 'in_progress']);

        DB::table('appointments')
            ->whereIn('status', ['no-show', 'no show'])
            ->update(['status' => 'no_show']);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY status VARCHAR(255) NOT NULL DEFAULT 'scheduled'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE appointments ALTER COLUMN status SET DEFAULT 'scheduled'");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY status VARCHAR(255) NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE appointments ALTER COLUMN status SET DEFAULT 'pending'");
        }
    }
};

