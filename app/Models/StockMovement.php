<?php

namespace App\Models;

use App\User;
use Eloquent;

/** Every change to stock: restocks in, sales out, returns and adjustments. */
class StockMovement extends Eloquent
{
    public const TYPES = ['restock' => 'Restock', 'sale' => 'Sale', 'return' => 'Return', 'adjust' => 'Adjustment'];

    protected $fillable = ['item_id', 'qty', 'type', 'unit_cost', 'charge_id', 'note', 'user_id', 'date'];

    protected $casts = ['date' => 'date'];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
