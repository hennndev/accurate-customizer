<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'retention_days',
        'migrate_per_page',
    ];
    
    protected $casts = [
        'retention_days' => 'integer',
        'migrate_per_page' => 'integer',
    ];
}
