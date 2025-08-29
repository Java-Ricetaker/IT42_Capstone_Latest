<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit',
        'unit_hint',
        'reorder_level',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reorder_level' => 'decimal:2',
    ];

    public function batches()
    {
        return $this->hasMany(InventoryBatch::class, 'item_id');
    }

    // Convenience: computed current stock from batches
    public function getCurrentStockAttribute()
    {
        return $this->batches()->sum('qty_on_hand');
    }
}
