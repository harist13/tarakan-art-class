<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\Transaction;
use App\Observers\InventoryItemObserver;
use Illuminate\Console\Command;

/**
 * Mencatat belanja barang inventaris yang sudah ada SEBELUM fitur pencatatan
 * otomatis aktif. Dijalankan manual (bukan lewat migrasi) karena menambah
 * pengeluaran ke bulan-bulan yang sudah lewat.
 */
class BackfillInventoryExpenses extends Command
{
    protected $signature = 'inventory:backfill-expenses {--dry-run : Tampilkan rencana tanpa menyimpan}';

    protected $description = 'Catat pengeluaran untuk barang inventaris lama yang belum tercatat di Laporan Keuangan';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $items = InventoryItem::query()
            ->with('movements')
            ->whereDoesntHave('transactions')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            $this->info('Tidak ada barang yang perlu dicatat. Semua sudah sinkron.');

            return self::SUCCESS;
        }

        $rows = [];
        $total = 0.0;

        foreach ($items as $item) {
            $quantity = $this->initialStock($item);
            $price = (float) $item->purchase_price;
            $amount = $quantity * $price;

            if ($quantity < 1 || $price <= 0) {
                $rows[] = [$item->item_code, $item->item_name, $quantity, 'dilewati (stok awal / harga beli 0)'];

                continue;
            }

            $rows[] = [$item->item_code, $item->item_name, $quantity, 'Rp '.number_format($amount, 0, ',', '.')];
            $total += $amount;

            if ($dryRun) {
                continue;
            }

            Transaction::create([
                'type' => 'expense',
                'category' => InventoryItemObserver::CATEGORY,
                'amount' => $amount,
                // Tanggal barang dibuat, agar masuk ke periode yang benar.
                'transaction_date' => $item->created_at->toDateString(),
                'description' => InventoryItemObserver::describe($item, $quantity),
                'inventory_item_id' => $item->id,
                'recorded_by' => null,
            ]);
        }

        $this->table(['Kode', 'Barang', 'Stok Awal', 'Pengeluaran'], $rows);
        $this->info(($dryRun ? '[dry-run] Akan dicatat' : 'Tercatat').': Rp '.number_format($total, 0, ',', '.'));

        return self::SUCCESS;
    }

    /**
     * Stok awal saat barang dibuat = stok sekarang, dibalik dari pergerakan
     * stok yang sudah terjadi setelahnya.
     */
    private function initialStock(InventoryItem $item): int
    {
        $in = $item->movements->where('type', 'in')->sum('quantity');
        $out = $item->movements->where('type', 'out')->sum('quantity');

        return (int) $item->remaining_stock - (int) $in + (int) $out;
    }
}
