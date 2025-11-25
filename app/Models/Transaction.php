<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function accurateDatabase()
    {
        return $this->belongsTo(AccurateDatabase::class, 'accurate_database_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
}
