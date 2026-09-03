<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /**
     * Pilihan kategori untuk dropdown form. Kategori bawaan observer
     * (pembayaran, inventaris, penjualan barang) ikut didaftarkan supaya transaksi
     * otomatis tetap bisa diedit tanpa terpaksa ganti kategori.
     */
    public const CATEGORIES = [
        'SPP / Pembayaran Kelas',
        'Penjualan Barang',
        'Gaji Tutor',
        'Perlengkapan',
        'Operasional',
        'Pembelian Inventaris',
    ];

    /**
     * Kategori yang hanya boleh dipakai Super Admin. Nominal gaji tutor termasuk data
     * sensitif, jadi admin biasa tidak melihatnya di dropdown dan tidak boleh
     * mengubah/menghapus transaksinya.
     */
    public const SUPER_ADMIN_CATEGORIES = [
        'Gaji Tutor',
    ];

    protected $fillable = [
        'type',
        'category',
        'amount',
        'transaction_date',
        'description',
        'payment_id',
        'inventory_item_id',
        'recorded_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
