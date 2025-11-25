<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'accurate_database_id',
        'name',
        'slug',
        'icon',
        'description',
        'accurate_endpoint',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function accurateDatabase()
    {
        return $this->belongsTo(AccurateDatabase::class, 'accurate_database_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'module_id');
    }
}
