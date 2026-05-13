<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'captured_at' => 'datetime',
        'last_pushed_at' => 'datetime',
        'push_count' => 'integer',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PUSHED_CREATE = 'pushed_create';
    const STATUS_FAILED = 'failed';

    public function accurateDatabase()
    {
        return $this->belongsTo(AccurateDatabase::class, 'accurate_database_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
}
