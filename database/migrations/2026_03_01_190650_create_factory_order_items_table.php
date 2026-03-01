<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('factory_order_items')) {
            return;
        }

        Schema::create('factory_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_order_id')->constrained('factory_orders')->cascadeOnDelete();
            $table->string('item_name');
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('tooth_number', 32)->nullable();
            $table->string('material')->nullable();
            $table->string('shade', 120)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->enum('status', ['ordered', 'in_progress', 'delivered', 'cancelled'])->default('ordered');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['factory_order_id', 'status'], 'factory_order_items_status_idx');
            $table->index('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_order_items');
    }
};
