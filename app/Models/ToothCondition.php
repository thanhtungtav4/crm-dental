<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ToothCondition extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'sort_order',
        'color',
        'description',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END")
            ->orderBy('sort_order')
            ->orderByRaw("CASE WHEN category IS NULL OR category = '' THEN 1 ELSE 0 END")
            ->orderBy('category')
            ->orderBy('code');
    }

    /**
     * Danh sách nhóm tình trạng lấy từ dữ liệu setting hiện có.
     */
    public static function getCategoryOptions(): array
    {
        $defaults = ['Bệnh lý', 'Phục hình', 'Hiện trạng', 'Khác'];

        $dynamic = static::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        $categories = array_values(array_unique([...$defaults, ...$dynamic]));

        return collect($categories)
            ->mapWithKeys(fn (string $category) => [$category => $category])
            ->all();
    }
}
