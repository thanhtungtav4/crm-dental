<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class Doctor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['branch_id', 'name', 'specialization', 'phone'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function appointments() { return $this->hasMany(Appointment::class); }
}

