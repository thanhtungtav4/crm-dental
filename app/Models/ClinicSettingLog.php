<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicSettingLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'clinic_setting_id',
        'setting_group',
        'setting_key',
        'setting_label',
        'old_value',
        'new_value',
        'is_secret',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'changed_at' => 'datetime',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ClinicSetting::class, 'clinic_setting_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

