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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Tên nhà cung cấp');
            $table->string('code', 50)->unique()->comment('Mã nhà cung cấp (VD: NCC001)');
            $table->string('tax_code', 50)->nullable()->comment('Mã số thuế');
            
            // Contact information
            $table->string('contact_person', 200)->nullable()->comment('Người liên hệ');
            $table->string('phone', 20)->nullable()->comment('Số điện thoại');
            $table->string('email', 255)->nullable()->comment('Email');
            $table->text('address')->nullable()->comment('Địa chỉ');
            $table->string('website', 255)->nullable()->comment('Website');
            
            // Business terms
            $table->enum('payment_terms', ['cash', 'cod', '7_days', '15_days', '30_days', '60_days', '90_days'])
                ->default('30_days')
                ->comment('Điều khoản thanh toán');
            $table->text('notes')->nullable()->comment('Ghi chú');
            
            // Status
            $table->boolean('active')->default(true)->comment('Đang hoạt động');
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('code');
            $table->index('active');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
