<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptureListItemId extends Model
{
    use HasFactory;

    protected $fillable = [
        'accurate_database_id',
        'module_slug',
        'params_hash',
        'list_item_id',
        'fallback_number',
        'captured_from_list_at',
    ];

    protected $casts = [
        'list_item_id' => 'integer',
        'captured_from_list_at' => 'datetime',
    ];
}
