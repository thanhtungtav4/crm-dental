<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'customer_id',
        'user_id',
        'type',
        'care_type',
        'care_channel',
        'care_status',
        'care_at',
        'care_mode',
        'is_recurring',
        'content',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'care_at' => 'datetime',
        'is_recurring' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
