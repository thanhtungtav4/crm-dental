<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitEpisode extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RESCHEDULED = 'rescheduled';

    public const DEFAULT_STATUS = self::STATUS_SCHEDULED;

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',
        'branch_id',
        'chair_code',
        'status',
        'scheduled_at',
        'check_in_at',
        'arrived_at',
        'in_chair_at',
        'check_out_at',
        'planned_duration_minutes',
        'actual_duration_minutes',
        'waiting_minutes',
        'chair_minutes',
        'overrun_minutes',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'check_in_at' => 'datetime',
        'arrived_at' => 'datetime',
        'in_chair_at' => 'datetime',
        'check_out_at' => 'datetime',
        'planned_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'waiting_minutes' => 'integer',
        'chair_minutes' => 'integer',
        'overrun_minutes' => 'integer',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
