<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\StockMovement;
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
            ->orderBy('item_name')
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

        DB::transaction(function () use ($data) {
            $item = InventoryItem::create($data);
            ActivityLog::record('created', $item, "Menambah barang {$item->item_name}");
        });

        return redirect()->route('inventory.index')->with('success', 'Barang berhasil ditambahkan.');
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
            'movement_date' => ['required', 'date'],
        ]);

        $data['recorded_by'] = auth()->id();

        try {
            DB::transaction(function () use ($data) {
                // Kunci baris item agar cek stok & penyesuaian stok aman dari race condition.
                $item = InventoryItem::lockForUpdate()->findOrFail($data['inventory_item_id']);

                if ($data['type'] === 'out' && $data['quantity'] > $item->remaining_stock) {
                    throw new \RuntimeException('Stok tidak mencukupi untuk pengeluaran barang.');
                }

                // Event "created" pada StockMovement otomatis menyesuaikan remaining_stock item.
                StockMovement::create($data);
                ActivityLog::record('created', $item, "Stok {$data['type']} {$item->item_name} ({$data['quantity']})");
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('inventory.index')->with('success', 'Pergerakan stok berhasil dicatat.');
    }
}
