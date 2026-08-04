<?php

namespace App\Observers;

use App\Models\InventoryItem;
use App\Models\Transaction;

/**
 * Pembelian barang inventaris (F10) otomatis tercatat sebagai PENGELUARAN
 * di Laporan Keuangan (F7).
 *
 * Berbeda dengan pemasukan dari invoice yang terus disinkronkan, pengeluaran ini
 * adalah catatan sekali jalan atas uang yang memang sudah keluar saat barang
 * dibeli. Revisi harga barang setelahnya dianggap harga baru untuk pembelian
 * berikutnya, bukan koreksi belanja yang sudah lewat — supaya total bulan yang
 * sudah ditutup tidak berubah diam-diam.
 */
class InventoryItemObserver
{
    public const CATEGORY = 'Pembelian Inventaris';

    public const DETACHED_NOTE = '(barang dihapus)';

    public function created(InventoryItem $item): void
    {
        $quantity = (int) $item->remaining_stock;
        $price = (float) $item->purchase_price;

        // Stok awal 0 atau barang tanpa harga beli (hibah/sumbangan) tidak
        // mengeluarkan uang, jadi tidak perlu dicatat.
        if ($quantity < 1 || $price <= 0) {
            return;
        }

        Transaction::create([
            'type' => 'expense',
            'category' => self::CATEGORY,
            'amount' => $price * $quantity,
            'transaction_date' => now()->toDateString(),
            'description' => self::describe($item, $quantity),
            'inventory_item_id' => $item->id,
            'recorded_by' => auth()->id(),
        ]);
    }

    /**
     * Barang dihapus tidak berarti uangnya kembali: catatan belanjanya
     * dipertahankan, hanya dilepas dari barangnya agar tetap bisa ditelusuri.
     */
    public function deleting(InventoryItem $item): void
    {
        foreach ($item->transactions()->get() as $transaction) {
            $transaction->update([
                'inventory_item_id' => null,
                'description' => trim("{$transaction->description} ".self::DETACHED_NOTE),
            ]);
        }
    }

    public static function describe(InventoryItem $item, int $quantity): string
    {
        $price = number_format((float) $item->purchase_price, 0, ',', '.');

        return "Pembelian {$item->item_name} ({$quantity} x Rp {$price})";
    }
}
