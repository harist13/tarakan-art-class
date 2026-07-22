<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $table = 'inventory_stock_movements';

    protected $fillable = [
        'inventory_item_id',
        'type',
        'quantity',
        'movement_date',
        'recorded_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (StockMovement $movement) {
            $movement->applyToStock();
        });
    }

    public function applyToStock(): void
    {
        $item = $this->item;
        if (! $item) {
            return;
        }

        if ($this->type === 'in') {
            $item->increment('remaining_stock', $this->quantity);
        } else {
            $item->decrement('remaining_stock', $this->quantity);
        }
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
