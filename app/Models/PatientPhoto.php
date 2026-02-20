<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientPhoto extends Model
{
    protected $fillable = [
        'patient_id',
        'type', // normal, ortho, xray
        'date',
        'title',
        'content',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'content' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
