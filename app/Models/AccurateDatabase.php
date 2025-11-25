<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccurateDatabase extends Model
{
    use HasFactory;

    protected $fillable = [
        'db_id',
        'db_name',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'accurate_database_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class, 'accurate_database_id');
    }
}
