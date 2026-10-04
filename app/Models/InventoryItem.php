<?php

namespace App\Models;

use Eloquent;

/** Something the school shop sells: uniform, socks, sweaters, books, stationery… */
class InventoryItem extends Eloquent
{
    public const CATEGORIES = ['Uniform', 'Books & stationery', 'Accessories', 'Other'];

    protected $fillable = ['name', 'category', 'price', 'stock', 'reorder_level', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }
}
