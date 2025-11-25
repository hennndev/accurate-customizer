<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccurateConnection extends Model
{
    use HasFactory;

    /**
     * Atribut yang boleh diisi secara massal.
     */
    protected $fillable = [
        'alias',
        'client_id',
        'client_secret',
    ];

    /**
     * Atribut yang harus di-casting (diubah tipenya).
     * 'client_secret' akan otomatis dienkripsi/dekripsi.
     */
    protected $casts = [
        'client_secret' => 'encrypted',
    ];
}