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

    public function getEntityNameAttribute(): ?string
    {
        if (!empty($this->attributes['entity_name_raw'])) {
            return $this->attributes['entity_name_raw'];
        }

        $data = is_string($this->data) ? json_decode($this->data, true) : (array) $this->data;
        if (!is_array($data)) {
            return null;
        }

        $val = $data['name']
            ?? $data['customer']['name']
            ?? $data['vendor']['name']
            ?? $data['customerName']
            ?? $data['vendorName']
            ?? $data['itemName']
            ?? $data['item']['name']
            ?? $data['payTo']
            ?? $data['receivedFrom']
            ?? $data['description']
            ?? $data['memo']
            ?? null;

        if (is_string($val)) {
            $val = trim($val);
            return $val !== '' ? $val : null;
        }

        return null;
    }
}
