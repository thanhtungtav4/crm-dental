<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'full_name',
        'phone',
        'email',
    'address',
        'source',
        'status',
    'assigned_to',
    'next_follow_up_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function convertToPatient(): Patient
    {
        if ($this->patient) {
            return $this->patient;
        }

        $patient = Patient::create([
            'customer_id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'first_branch_id' => $this->branch_id,
        ]);

        $this->update(['status' => 'converted']);

        return $patient;
    }
}
