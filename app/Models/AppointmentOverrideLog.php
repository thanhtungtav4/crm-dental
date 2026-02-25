<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentOverrideLog extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'appointment_id',
        'override_type',
        'reason',
        'actor_id',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
