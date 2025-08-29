<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBatch extends Model
{
    protected $fillable = [
        'item_id','lot_no','expiry_date','supplier','unit_cost','qty_on_hand','received_at'
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_at' => 'date',
        'unit_cost' => 'decimal:2',
        'qty_on_hand' => 'decimal:2',
    ];

    public function item() {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function movements() {
        return $this->hasMany(InventoryMovement::class, 'batch_id');
    }
}
