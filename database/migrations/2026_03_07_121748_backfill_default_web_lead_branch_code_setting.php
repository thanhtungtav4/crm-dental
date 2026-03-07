<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinic_settings') || ! Schema::hasTable('branches')) {
            return;
        }

        $defaultBranchCode = DB::table('branches')
            ->where('active', true)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('id')
            ->value('code');

        if (! is_string($defaultBranchCode) || trim($defaultBranchCode) === '') {
            return;
        }

        DB::table('clinic_settings')
            ->where('key', 'web_lead.default_branch_code')
            ->where(function ($query): void {
                $query->whereNull('value')
                    ->orWhere('value', '');
            })
            ->update([
                'value' => $defaultBranchCode,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No rollback: backfilled value may already be used as runtime default.
    }
};
