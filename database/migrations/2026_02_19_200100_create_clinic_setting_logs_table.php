<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('clinic_setting_logs')) {
            return;
        }

        Schema::create('clinic_setting_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_setting_id')
                ->nullable()
                ->constrained('clinic_settings')
                ->nullOnDelete();
            $table->string('setting_group', 100)->index();
            $table->string('setting_key', 150)->index();
            $table->string('setting_label')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_setting_logs');
    }
};

