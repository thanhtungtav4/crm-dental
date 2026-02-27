<?php

use App\Services\ActionPermissionBaselineService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(ActionPermissionBaselineService::class)->sync();
    }

    public function down(): void
    {
        //
    }
};
