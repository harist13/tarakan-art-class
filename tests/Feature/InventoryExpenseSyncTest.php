<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\InventoryItemObserver;
use App\Observers\StockMovementObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: sinkronisasi Inventaris (F10) → Laporan Keuangan (F7).
 * Barang yang dibeli tercatat otomatis sebagai pengeluaran sebesar
 * stok awal x harga beli.
 */
class InventoryExpenseSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        Role::firstOrCreate(['name' => 'super_admin']);
        $user = User::create([
            'full_name' => 'Admin QA',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'item_name' => 'Cat Air',
            'purchase_price' => 25000,
            'selling_price' => 35000,
            'remaining_stock' => 10,
        ], $overrides);
    }

    public function test_tambah_barang_otomatis_jadi_pengeluaran(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('inventory.store'), $this->payload())
            ->assertRedirect(route('inventory.index'));

        $item = InventoryItem::firstOrFail();

        $this->assertDatabaseHas('transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'expense',
            'category' => InventoryItemObserver::CATEGORY,
            'amount' => 250000, // 10 x 25.000
            'recorded_by' => $user->id,
        ]);

        // Muncul di tabel menu Laporan Keuangan.
        $this->actingAs($user)
            ->get(route('financials.index'))
            ->assertOk()
            ->assertSee('Pembelian Cat Air (10 x Rp 25.000)')
            ->assertSee('Rp 250.000');
    }

    public function test_stok_awal_nol_tidak_dicatat(): void
    {
        $this->actingAs($this->makeUser())
            ->post(route('inventory.store'), $this->payload(['remaining_stock' => 0]));

        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_harga_beli_nol_tidak_dicatat(): void
    {
        $this->actingAs($this->makeUser())
            ->post(route('inventory.store'), $this->payload(['purchase_price' => 0]));

        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_revisi_harga_tidak_mengubah_belanja_yang_sudah_tercatat(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload());
        $item = InventoryItem::firstOrFail();

        $this->actingAs($user)->put(route('inventory.update', $item), [
            'item_name' => 'Cat Air',
            'purchase_price' => 40000,
            'selling_price' => 50000,
        ]);

        // Harga baru berlaku untuk pembelian berikutnya; belanja lama tetap 250.000.
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'inventory_item_id' => $item->id,
            'amount' => 250000,
        ]);
    }

    public function test_hapus_barang_mempertahankan_pengeluaran_tapi_melepasnya(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload());
        $item = InventoryItem::firstOrFail();

        $this->actingAs($user)
            ->delete(route('inventory.destroy', $item))
            ->assertRedirect(route('inventory.index'));

        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseCount('transactions', 1);

        $transaction = Transaction::firstOrFail();
        $this->assertNull($transaction->inventory_item_id);
        $this->assertSame(
            'Pembelian Cat Air (10 x Rp 25.000) '.InventoryItemObserver::DETACHED_NOTE,
            $transaction->description
        );
    }

    public function test_restok_yang_ditandai_pembelian_jadi_pengeluaran(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload(['remaining_stock' => 0]));
        $item = InventoryItem::firstOrFail();
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($user)
            ->post(route('inventory.movement'), [
                'inventory_item_id' => $item->id,
                'type' => 'in',
                'quantity' => 20,
                'is_purchase' => 1,
                'movement_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('inventory.index'));

        $this->assertDatabaseHas('transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'expense',
            'amount' => 500000, // 20 x 25.000
        ]);
        $this->assertSame(20, $item->fresh()->remaining_stock);
    }

    public function test_restok_tanpa_tanda_pembelian_tidak_dicatat(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload(['remaining_stock' => 0]));
        $item = InventoryItem::firstOrFail();

        $this->actingAs($user)->post(route('inventory.movement'), [
            'inventory_item_id' => $item->id,
            'type' => 'in',
            'quantity' => 5,
            'movement_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(5, $item->fresh()->remaining_stock);
    }

    public function test_stok_keluar_tidak_pernah_jadi_pengeluaran(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload(['purchase_price' => 0]));
        $item = InventoryItem::firstOrFail();

        // is_purchase sengaja dikirim untuk memastikan diabaikan pada tipe "out".
        $this->actingAs($user)->post(route('inventory.movement'), [
            'inventory_item_id' => $item->id,
            'type' => 'out',
            'quantity' => 3,
            'is_purchase' => 1,
            'movement_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'out',
            'is_purchase' => false,
        ]);
    }

    public function test_penjualan_barang_jadi_pemasukan_sebesar_harga_jual(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload());
        $item = InventoryItem::firstOrFail();

        $this->actingAs($user)
            ->post(route('inventory.movement'), [
                'inventory_item_id' => $item->id,
                'type' => 'out',
                'quantity' => 4,
                'is_sale' => 1,
                'movement_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('inventory.index'));

        // Pemasukan memakai harga JUAL (35.000), bukan harga beli.
        $this->assertDatabaseHas('transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'income',
            'category' => StockMovementObserver::SALE_CATEGORY,
            'amount' => 140000, // 4 x 35.000
        ]);
        $this->assertSame(6, $item->fresh()->remaining_stock);

        // Belanja awal tetap tercatat terpisah sebagai pengeluaran.
        $this->assertDatabaseHas('transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'expense',
            'amount' => 250000,
        ]);

        $this->actingAs($user)
            ->get(route('financials.index'))
            ->assertOk()
            ->assertSee('Penjualan Cat Air (4 x Rp 35.000)');
    }

    public function test_stok_keluar_tanpa_tanda_penjualan_tidak_dicatat(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload(['purchase_price' => 0]));
        $item = InventoryItem::firstOrFail();

        // Barang rusak / dipakai sendiri: stok berkurang, keuangan tidak tersentuh.
        $this->actingAs($user)->post(route('inventory.movement'), [
            'inventory_item_id' => $item->id,
            'type' => 'out',
            'quantity' => 2,
            'movement_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(8, $item->fresh()->remaining_stock);
    }

    public function test_stok_masuk_tidak_pernah_jadi_pemasukan(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload(['remaining_stock' => 0]));
        $item = InventoryItem::firstOrFail();

        // is_sale sengaja dikirim untuk memastikan diabaikan pada tipe "in".
        $this->actingAs($user)->post(route('inventory.movement'), [
            'inventory_item_id' => $item->id,
            'type' => 'in',
            'quantity' => 5,
            'is_sale' => 1,
            'movement_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'in',
            'is_sale' => false,
        ]);
    }

    public function test_penjualan_barang_tanpa_harga_jual_tidak_dicatat(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload([
            'purchase_price' => 0,
            'selling_price' => 0,
        ]));
        $item = InventoryItem::firstOrFail();

        $this->actingAs($user)->post(route('inventory.movement'), [
            'inventory_item_id' => $item->id,
            'type' => 'out',
            'quantity' => 2,
            'is_sale' => 1,
            'movement_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_backfill_mencatat_barang_lama_memakai_stok_awal(): void
    {
        $user = $this->makeUser();

        // Barang dibuat tanpa pencatatan (mensimulasikan data sebelum fitur ini ada).
        $item = InventoryItem::withoutEvents(fn () => InventoryItem::create([
            'item_code' => 'INVT001',
            'item_name' => 'Kuas Lukis',
            'purchase_price' => 15000,
            'selling_price' => 20000,
            'remaining_stock' => 10,
        ]));

        // Lalu ada pergerakan stok: +5 dan -3, sehingga stok sekarang 12.
        $this->actingAs($user)->post(route('inventory.movement'), [
            'inventory_item_id' => $item->id, 'type' => 'in', 'quantity' => 5,
            'movement_date' => now()->toDateString(),
        ]);
        $this->actingAs($user)->post(route('inventory.movement'), [
            'inventory_item_id' => $item->id, 'type' => 'out', 'quantity' => 3,
            'movement_date' => now()->toDateString(),
        ]);
        $this->assertSame(12, $item->fresh()->remaining_stock);

        $this->artisan('inventory:backfill-expenses')->assertSuccessful();

        // Stok awal dihitung balik: 12 - 5 + 3 = 10 → 10 x 15.000.
        $this->assertDatabaseHas('transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'expense',
            'amount' => 150000,
        ]);

        // Dijalankan ulang tidak menggandakan.
        $this->artisan('inventory:backfill-expenses')->assertSuccessful();
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_pengeluaran_inventaris_masih_bisa_dikoreksi_manual(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('inventory.store'), $this->payload());
        $transaction = Transaction::firstOrFail();

        // Beda dengan pemasukan dari invoice: belanja inventaris boleh dikoreksi
        // (mis. menambahkan ongkos kirim) karena tidak ada sumber yang disinkronkan.
        $this->actingAs($user)
            ->put(route('financials.update', $transaction), [
                'type' => 'expense',
                'category' => InventoryItemObserver::CATEGORY,
                'amount' => 275000,
                'transaction_date' => now()->toDateString(),
                'description' => 'Pembelian Cat Air + ongkir',
            ])
            ->assertRedirect(route('financials.index'));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 275000,
        ]);
    }
}
