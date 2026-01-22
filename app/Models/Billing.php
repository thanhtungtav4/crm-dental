<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Billing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['treatment_plan_id', 'amount', 'status'];

    public function treatmentPlan() { return $this->belongsTo(TreatmentPlan::class); }
}
