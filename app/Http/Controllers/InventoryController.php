<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Observers\StockMovementObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $items = InventoryItem::query()
            ->when($search, fn ($q) => $q->where('item_name', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%"))
            // Barang yang baru ditambahkan tampil paling atas; id menurun jadi pemecah
            // kalau ada beberapa barang yang dibuat pada detik yang sama.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $movements = StockMovement::with(['item', 'recorder'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('inventory.index', compact('items', 'movements', 'search'));
    }

    public function create()
    {
        return view('inventory.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'remaining_stock' => ['required', 'integer', 'min:0'],
        ]);

        // Belanjanya otomatis dicatat sebagai pengeluaran oleh InventoryItemObserver.
        $item = DB::transaction(function () use ($data) {
            $item = InventoryItem::create($data);
            ActivityLog::record('created', $item, "Menambah barang {$item->item_name}");

            return $item;
        });

        $message = 'Barang berhasil ditambahkan.';
        if ($expense = $item->transactions()->sum('amount')) {
            $message .= ' Pengeluaran Rp '.number_format($expense, 0, ',', '.').' tercatat di Laporan Keuangan.';
        }

        return redirect()->route('inventory.index')->with('success', $message);
    }

    public function edit(InventoryItem $inventory)
    {
        return view('inventory.edit', ['item' => $inventory]);
    }

    public function update(Request $request, InventoryItem $inventory)
    {
        $data = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($inventory, $data) {
            $inventory->update($data);
            ActivityLog::record('updated', $inventory, "Memperbarui barang {$inventory->item_name}");
        });

        return redirect()->route('inventory.index')->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(InventoryItem $inventory)
    {
        DB::transaction(function () use ($inventory) {
            ActivityLog::record('deleted', $inventory, "Menghapus barang {$inventory->item_name}");
            $inventory->delete();
        });

        return redirect()->route('inventory.index')->with('success', 'Barang berhasil dihapus.');
    }

    public function storeMovement(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'type' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'is_purchase' => ['nullable', 'boolean'],
            'is_sale' => ['nullable', 'boolean'],
            'movement_date' => ['required', 'date'],
        ]);

        $data['recorded_by'] = auth()->id();
        // Pembelian hanya mungkin pada stok masuk, penjualan hanya pada stok keluar.
        $data['is_purchase'] = $data['type'] === 'in' && $request->boolean('is_purchase');
        $data['is_sale'] = $data['type'] === 'out' && $request->boolean('is_sale');

        try {
            $movement = DB::transaction(function () use ($data) {
                // Kunci baris item agar cek stok & penyesuaian stok aman dari race condition.
                $item = InventoryItem::lockForUpdate()->findOrFail($data['inventory_item_id']);

                if ($data['type'] === 'out' && $data['quantity'] > $item->remaining_stock) {
                    throw new \RuntimeException('Stok tidak mencukupi untuk pengeluaran barang.');
                }

                // Event "created" pada StockMovement menyesuaikan remaining_stock item,
                // sekaligus mencatat pengeluaran bila ditandai sebagai pembelian.
                $movement = StockMovement::create($data);
                ActivityLog::record('created', $item, "Stok {$data['type']} {$item->item_name} ({$data['quantity']})");

                return $movement;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $message = 'Pergerakan stok berhasil dicatat.';
        if ($expense = StockMovementObserver::purchaseAmount($movement)) {
            $message .= ' Pengeluaran Rp '.number_format($expense, 0, ',', '.').' tercatat di Laporan Keuangan.';
        }
        if ($income = StockMovementObserver::saleAmount($movement)) {
            $message .= ' Pemasukan Rp '.number_format($income, 0, ',', '.').' tercatat di Laporan Keuangan.';
        }

        return redirect()->route('inventory.index')->with('success', $message);
    }
}
