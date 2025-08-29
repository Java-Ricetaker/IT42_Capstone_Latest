<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'batch_id',
        'type',
        'qty',
        'reason',
        'ref_type',
        'ref_id',
        'user_id'
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    public function batch()
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function ref()
    {
        return $this->morphTo();
    }
}
