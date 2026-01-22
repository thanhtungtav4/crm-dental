<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // Material categorization
            $table->enum('category', ['medicine', 'consumable', 'equipment', 'dental_material'])
                ->after('unit')
                ->default('consumable')
                ->comment('Phân loại vật tư: thuốc, tiêu hao, thiết bị, vật liệu nha');

            // Supplier information
            $table->string('manufacturer', 200)->nullable()->after('category')->comment('Nhà sản xuất');
            $table->foreignId('supplier_id')->nullable()->after('manufacturer')->constrained('suppliers')->nullOnDelete()->comment('Nhà cung cấp chính');

            // Inventory management
            $table->integer('reorder_point')->nullable()->after('min_stock')->comment('Điểm đặt hàng lại (khi số lượng <= reorder_point thì cần đặt hàng)');
            $table->string('storage_location', 100)->nullable()->after('reorder_point')->comment('Vị trí lưu kho (VD: Kệ A-01, Tủ lạnh B)');

            // Pricing
            $table->decimal('cost_price', 15, 2)->nullable()->after('unit_price')->comment('Giá nhập (cost)');
            $table->renameColumn('unit_price', 'sale_price');

            // Indexes for better performance
            $table->index('category');
            $table->index('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['category']);
            $table->dropIndex(['supplier_id']);
            $table->dropColumn([
                'category',
                'manufacturer',
                'supplier_id',
                'reorder_point',
                'storage_location',
                'cost_price',
            ]);

            $table->renameColumn('sale_price', 'unit_price');
        });
    }
};
