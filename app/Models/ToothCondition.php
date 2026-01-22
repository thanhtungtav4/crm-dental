<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToothCondition extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'color',
        'description',
    ];
}
