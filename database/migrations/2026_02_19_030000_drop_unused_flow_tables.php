<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('appointment_reminders');
        Schema::dropIfExists('customer_interactions');
        Schema::dropIfExists('duplicate_detections');
        Schema::dropIfExists('record_merges');
        Schema::dropIfExists('identification_logs');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('payment_reminders');
    }

    public function down(): void
    {
        // Intentionally left empty.
        // These tables were removed because they are outside the reference flow.
    }
};
