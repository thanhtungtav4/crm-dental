<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factory_orders', function (Blueprint $table): void {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('vendor_name')
                ->constrained('suppliers')
                ->nullOnDelete();

            $table->index(['supplier_id', 'status', 'due_at'], 'factory_orders_supplier_status_due_idx');
        });

        $supplierIdsByNormalizedName = DB::table('suppliers')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (object $supplier): array {
                $normalizedName = $this->normalizeVendorName($supplier->name);

                if ($normalizedName === null) {
                    return [];
                }

                return [$normalizedName => (int) $supplier->id];
            })
            ->all();

        $nextAutoSupplierSequence = $this->resolveNextAutoSupplierSequence();

        DB::table('factory_orders')
            ->select(['id', 'vendor_name'])
            ->whereNull('supplier_id')
            ->whereNotNull('vendor_name')
            ->orderBy('id')
            ->chunkById(100, function (Collection $orders) use (&$supplierIdsByNormalizedName, &$nextAutoSupplierSequence): void {
                foreach ($orders as $order) {
                    $normalizedVendorName = $this->normalizeVendorName($order->vendor_name);

                    if ($normalizedVendorName === null) {
                        continue;
                    }

                    $supplierId = $supplierIdsByNormalizedName[$normalizedVendorName]
                        ??= $this->createAutoSupplier($normalizedVendorName, $nextAutoSupplierSequence);

                    DB::table('factory_orders')
                        ->where('id', $order->id)
                        ->update([
                            'supplier_id' => $supplierId,
                            'vendor_name' => $normalizedVendorName,
                            'updated_at' => now(),
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('factory_orders', function (Blueprint $table): void {
            $table->dropIndex('factory_orders_supplier_status_due_idx');
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }

    private function normalizeVendorName(?string $value): ?string
    {
        $normalizedValue = $value !== null
            ? trim((string) Str::of($value)->squish())
            : null;

        return filled($normalizedValue) ? $normalizedValue : null;
    }

    private function resolveNextAutoSupplierSequence(): int
    {
        $lastAutoCode = DB::table('suppliers')
            ->where('code', 'like', 'LABAUTO%')
            ->orderByDesc('code')
            ->value('code');

        if (is_string($lastAutoCode) && preg_match('/LABAUTO(\d{4})$/', $lastAutoCode, $matches) === 1) {
            return ((int) ($matches[1] ?? 0)) + 1;
        }

        return 1;
    }

    private function createAutoSupplier(string $name, int &$sequence): int
    {
        do {
            $code = sprintf('LABAUTO%04d', $sequence++);
        } while (DB::table('suppliers')->where('code', $code)->exists());

        return (int) DB::table('suppliers')->insertGetId([
            'name' => $name,
            'code' => $code,
            'payment_terms' => '30_days',
            'notes' => 'Tu dong tao khi backfill supplier canonical cho factory orders.',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
