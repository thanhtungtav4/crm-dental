<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'from_branch_id',
        'to_branch_id',
        'moved_by',
        'note',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function mover()
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
