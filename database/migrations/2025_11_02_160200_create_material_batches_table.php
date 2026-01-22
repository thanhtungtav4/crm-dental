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
        Schema::create('material_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete()->comment('Vật tư');
            $table->string('batch_number', 50)->comment('Số lô sản xuất');
            $table->date('expiry_date')->comment('Hạn sử dụng (HSD)');
            $table->integer('quantity')->default(0)->comment('Số lượng trong lô này');
            $table->decimal('purchase_price', 15, 2)->nullable()->comment('Giá nhập của lô này');
            $table->date('received_date')->comment('Ngày nhận hàng');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete()->comment('Nhà cung cấp của lô này');
            $table->enum('status', ['active', 'expired', 'recalled', 'depleted'])
                ->default('active')
                ->comment('Trạng thái: active=đang dùng, expired=hết hạn, recalled=thu hồi, depleted=đã hết');
            $table->text('notes')->nullable()->comment('Ghi chú về lô hàng');
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('material_id');
            $table->index('batch_number');
            $table->index('expiry_date');
            $table->index('status');
            $table->index(['material_id', 'status']); // Composite for active batches per material
            
            // Unique constraint: same batch number for same material should be unique
            $table->unique(['material_id', 'batch_number'], 'material_batch_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_batches');
    }
};
