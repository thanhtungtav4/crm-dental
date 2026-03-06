<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class FactoryOrderItem extends Model
{
    protected $fillable = [
        'factory_order_id',
        'item_name',
        'service_id',
        'tooth_number',
        'material',
        'shade',
        'quantity',
        'unit_price',
        'total_price',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factory_order_id' => 'integer',
            'service_id' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $order = FactoryOrder::query()
                ->select(['id', 'status'])
                ->find((int) $item->factory_order_id);

            if (! $order instanceof FactoryOrder) {
                throw ValidationException::withMessages([
                    'factory_order_id' => 'Không tìm thấy lệnh labo để cập nhật hạng mục.',
                ]);
            }

            $order->assertItemsEditable();

            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = (float) ($item->unit_price ?? 0);
            $item->total_price = round(max($quantity, 0) * max($unitPrice, 0), 2);
        });

        static::deleting(function (self $item): void {
            $order = FactoryOrder::query()
                ->select(['id', 'status'])
                ->find((int) $item->factory_order_id);

            if (! $order instanceof FactoryOrder) {
                throw ValidationException::withMessages([
                    'factory_order_id' => 'Không tìm thấy lệnh labo để cập nhật hạng mục.',
                ]);
            }

            $order->assertItemsEditable();
        });
    }

    public function factoryOrder(): BelongsTo
    {
        return $this->belongsTo(FactoryOrder::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
