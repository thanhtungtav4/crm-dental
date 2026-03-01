<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientPhoto extends Model
{
    public const TYPE_NORMAL = 'normal';

    public const TYPE_EXTERNAL = 'ext';

    public const TYPE_INTERNAL = 'int';

    public const TYPE_XRAY = 'xray';

    protected $fillable = [
        'patient_id',
        'type', // normal, ext, int, xray
        'date',
        'title',
        'content',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'content' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_NORMAL => 'Thông thường',
            self::TYPE_EXTERNAL => 'Ảnh ngoài miệng',
            self::TYPE_INTERNAL => 'Ảnh trong miệng',
            self::TYPE_XRAY => 'X-quang',
        ];
    }
}
