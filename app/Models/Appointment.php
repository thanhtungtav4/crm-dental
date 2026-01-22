<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'patient_id',
        'doctor_id',
        'assigned_to',
        'branch_id',
        'date',
        'appointment_type',
        'duration_minutes',
        'status',
        'note',
        'chief_complaint',
        'internal_notes',
        'reminder_hours',
        'confirmed_at',
        'confirmed_by'
    ];

    protected $casts = [
        'date' => 'datetime',
        'confirmed_at' => 'datetime',
        'duration_minutes' => 'integer',
        'reminder_hours' => 'integer',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function doctor() { return $this->belongsTo(User::class, 'doctor_id'); }
    public function assignedTo() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function confirmedBy() { return $this->belongsTo(User::class, 'confirmed_by'); }
}
