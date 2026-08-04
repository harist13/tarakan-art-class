<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Models\Transaction;

/**
 * Menyambungkan pergerakan stok (F10) ke Laporan Keuangan (F7):
 * - stok masuk bertanda pembelian  → PENGELUARAN sebesar jumlah x harga beli;
 * - stok keluar bertanda terjual   → PEMASUKAN sebesar jumlah x harga jual.
 *
 * Pergerakan tanpa tanda sengaja tidak dicatat karena tidak ada uang yang
 * berpindah: stok masuk bisa berasal dari retur/koreksi hitung, dan stok keluar
 * bisa karena barang rusak, hilang, atau dipakai sendiri untuk kegiatan kelas.
 */
class StockMovementObserver
{
    public const SALE_CATEGORY = 'Penjualan Barang';

    public function created(StockMovement $movement): void
    {
        $this->recordPurchase($movement);
        $this->recordSale($movement);
    }

    private function recordPurchase(StockMovement $movement): void
    {
        $amount = self::purchaseAmount($movement);

        if ($amount <= 0) {
            return;
        }

        $item = $movement->item;
        $price = number_format((float) $item->purchase_price, 0, ',', '.');

        $this->record($movement, [
            'type' => 'expense',
            'category' => InventoryItemObserver::CATEGORY,
            'amount' => $amount,
            'description' => "Restok {$item->item_name} ({$movement->quantity} x Rp {$price})",
        ]);
    }

    private function recordSale(StockMovement $movement): void
    {
        $amount = self::saleAmount($movement);

        if ($amount <= 0) {
            return;
        }

        $item = $movement->item;
        $price = number_format((float) $item->selling_price, 0, ',', '.');

        $this->record($movement, [
            'type' => 'income',
            'category' => self::SALE_CATEGORY,
            'amount' => $amount,
            'description' => "Penjualan {$item->item_name} ({$movement->quantity} x Rp {$price})",
        ]);
    }

    private function record(StockMovement $movement, array $attributes): void
    {
        Transaction::create($attributes + [
            'transaction_date' => $movement->movement_date,
            'inventory_item_id' => $movement->inventory_item_id,
            'recorded_by' => $movement->recorded_by ?? auth()->id(),
        ]);
    }

    /**
     * Nilai belanja sebuah pergerakan stok. 0 berarti bukan pembelian
     * (stok keluar, retur/koreksi, atau barang tanpa harga beli).
     */
    public static function purchaseAmount(StockMovement $movement): float
    {
        if ($movement->type !== 'in' || ! $movement->is_purchase) {
            return 0.0;
        }

        $price = (float) ($movement->item?->purchase_price ?? 0);

        return $price > 0 ? $price * $movement->quantity : 0.0;
    }

    /**
     * Nilai penjualan sebuah pergerakan stok. 0 berarti bukan penjualan
     * (stok masuk, barang rusak/hilang/dipakai sendiri, atau tanpa harga jual).
     */
    public static function saleAmount(StockMovement $movement): float
    {
        if ($movement->type !== 'out' || ! $movement->is_sale) {
            return 0.0;
        }

        $price = (float) ($movement->item?->selling_price ?? 0);

        return $price > 0 ? $price * $movement->quantity : 0.0;
    }
}
