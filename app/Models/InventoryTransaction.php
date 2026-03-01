<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'branch_id',
        'treatment_session_id',
        'material_issue_note_id',
        'type', // in|out|adjust
        'quantity',
        'unit_cost',
        'note',
        'created_by',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function session()
    {
        return $this->belongsTo(TreatmentSession::class, 'treatment_session_id');
    }

    public function issueNote()
    {
        return $this->belongsTo(MaterialIssueNote::class, 'material_issue_note_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
