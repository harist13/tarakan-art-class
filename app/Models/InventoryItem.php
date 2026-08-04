<?php

namespace App\Models;

use App\Observers\InventoryItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(InventoryItemObserver::class)]
class InventoryItem extends Model
{
    protected $fillable = [
        'item_code',
        'item_name',
        'purchase_price',
        'selling_price',
        'remaining_stock',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'remaining_stock' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryItem $item) {
            if (empty($item->item_code)) {
                $item->item_code = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        $last = self::orderByDesc('id')->first();
        $next = $last ? ((int) preg_replace('/\D/', '', $last->item_code)) + 1 : 1;

        return 'INVT'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Pengeluaran otomatis di Laporan Keuangan (dibuat oleh InventoryItemObserver).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
