<?php

namespace App\Services;

use App\Models\FactoryOrder;
use App\Models\FactoryOrderItem;
use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;

class InventorySupplyReportReadModelService
{
    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function materialInventoryQuery(?array $branchIds): Builder
    {
        return $this->applyBranchScope(
            Material::query()->with('supplier'),
            $branchIds,
            'branch_id',
        );
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{total_materials:int,low_stock:int}
     */
    public function materialInventorySummary(?array $branchIds, ?string $from, ?string $until): array
    {
        $query = $this->applyBranchScope(Material::query(), $branchIds, 'branch_id');

        if ($branchIds === []) {
            return [
                'total_materials' => 0,
                'low_stock' => 0,
            ];
        }

        $this->applyDateRange($query, 'created_at', $from, $until);

        return [
            'total_materials' => (int) (clone $query)->count(),
            'low_stock' => (int) (clone $query)->whereColumn('stock_qty', '<=', 'min_stock')->count(),
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function factoryOrderQuery(?array $branchIds, ?string $status = null, ?int $supplierId = null): Builder
    {
        $query = $this->applyBranchScope(
            FactoryOrder::query(),
            $branchIds,
            'branch_id',
        );

        if ($branchIds === []) {
            return $query;
        }

        return $query
            ->when(
                filled($status),
                fn (Builder $builder): Builder => $builder->where('status', (string) $status),
            )
            ->when(
                $supplierId !== null,
                fn (Builder $builder): Builder => $builder->where('supplier_id', $supplierId),
            )
            ->with(['patient', 'branch', 'doctor', 'supplier'])
            ->withCount('items')
            ->withSum('items as items_total_amount', 'total_price');
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{total_orders:int,open_orders:int,delivered_orders:int,total_value:float}
     */
    public function factoryOrderSummary(
        ?array $branchIds,
        ?string $from,
        ?string $until,
        ?string $status = null,
        ?int $supplierId = null,
    ): array {
        $query = $this->factoryOrderQuery($branchIds, $status, $supplierId);

        if ($branchIds === []) {
            return [
                'total_orders' => 0,
                'open_orders' => 0,
                'delivered_orders' => 0,
                'total_value' => 0.0,
            ];
        }

        $this->applyDateRange($query, 'created_at', $from, $until);

        $totalOrders = (int) (clone $query)->count();
        $openOrders = (int) (clone $query)
            ->whereIn('status', [
                FactoryOrder::STATUS_ORDERED,
                FactoryOrder::STATUS_IN_PROGRESS,
            ])
            ->count();
        $deliveredOrders = (int) (clone $query)
            ->where('status', FactoryOrder::STATUS_DELIVERED)
            ->count();
        $totalValue = (float) FactoryOrderItem::query()
            ->whereIn('factory_order_id', (clone $query)->select('id'))
            ->sum('total_price');

        return [
            'total_orders' => $totalOrders,
            'open_orders' => $openOrders,
            'delivered_orders' => $deliveredOrders,
            'total_value' => $totalValue,
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    protected function applyBranchScope(Builder $query, ?array $branchIds, string $column): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    protected function applyDateRange(Builder $query, string $column, ?string $from, ?string $until): Builder
    {
        if (filled($from)) {
            $query->whereDate($column, '>=', $from);
        }

        if (filled($until)) {
            $query->whereDate($column, '<=', $until);
        }

        return $query;
    }
}
