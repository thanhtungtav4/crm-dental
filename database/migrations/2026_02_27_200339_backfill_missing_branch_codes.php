<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches')) {
            $branches = DB::table('branches')
                ->select(['id', 'created_at'])
                ->whereNull('code')
                ->orWhere('code', '')
                ->orderBy('id')
                ->get();

            foreach ($branches as $branch) {
                $date = now()->format('Ymd');
                if (filled($branch->created_at)) {
                    $date = date('Ymd', strtotime((string) $branch->created_at));
                }

                do {
                    $suffix = Str::upper(Str::random(6));
                    $code = "BR-{$date}-{$suffix}";
                } while (DB::table('branches')->where('code', $code)->exists());

                DB::table('branches')
                    ->where('id', $branch->id)
                    ->update([
                        'code' => $code,
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('clinic_settings')) {
            DB::table('clinic_settings')
                ->where('key', 'web_lead.default_branch_id')
                ->delete();
        }
    }

    public function down(): void
    {
        // No rollback: generated codes become production identifiers.
    }
};
