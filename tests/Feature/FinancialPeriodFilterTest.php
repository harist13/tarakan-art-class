<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: filter periode menu Laporan Keuangan (F7).
 * Default membuka bulan berjalan; opsi "Semua Bulan" menampilkan seluruh periode.
 */
class FinancialPeriodFilterTest extends TestCase
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

    private function seedTransactions(): void
    {
        Transaction::create([
            'type' => 'income',
            'category' => 'SPP / Pembayaran Kelas',
            'amount' => 200000,
            'transaction_date' => now()->startOfMonth()->toDateString(),
            'description' => 'Pemasukan bulan ini',
        ]);

        Transaction::create([
            'type' => 'income',
            'category' => 'SPP / Pembayaran Kelas',
            'amount' => 350000,
            'transaction_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'description' => 'Pemasukan bulan lalu',
        ]);
    }

    public function test_default_menampilkan_seluruh_periode(): void
    {
        $this->seedTransactions();

        $this->actingAs($this->makeUser())
            ->get(route('financials.index'))
            ->assertOk()
            ->assertSee('Pemasukan bulan ini')
            ->assertSee('Pemasukan bulan lalu')
            ->assertSee('seluruh periode')
            // Total mencakup semua bulan: 200.000 + 350.000.
            ->assertSee('Rp 550.000');
    }

    public function test_bulan_berjalan_bisa_dipilih_lewat_tombol_bulan_ini(): void
    {
        $this->seedTransactions();

        $this->actingAs($this->makeUser())
            ->get(route('financials.index', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Pemasukan bulan ini')
            ->assertDontSee('Pemasukan bulan lalu')
            ->assertSee(now()->translatedFormat('F Y'))
            ->assertSee('Rp 200.000');
    }

    public function test_filter_bulan_yang_dikosongkan_kembali_ke_seluruh_periode(): void
    {
        $this->seedTransactions();

        $this->actingAs($this->makeUser())
            ->get(route('financials.index', ['month' => '']))
            ->assertOk()
            ->assertSee('Pemasukan bulan ini')
            ->assertSee('Pemasukan bulan lalu')
            ->assertSee('seluruh periode')
            ->assertSee('Rp 550.000');
    }

    public function test_bulan_tertentu_bisa_dipilih(): void
    {
        $this->seedTransactions();

        $this->actingAs($this->makeUser())
            ->get(route('financials.index', ['month' => now()->subMonthNoOverflow()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Pemasukan bulan lalu')
            ->assertDontSee('Pemasukan bulan ini')
            ->assertSee('Rp 350.000');
    }

    public function test_format_bulan_tidak_valid_diperlakukan_sebagai_semua_periode(): void
    {
        $this->seedTransactions();

        $this->actingAs($this->makeUser())
            ->get(route('financials.index', ['month' => 'bukan-bulan']))
            ->assertOk()
            ->assertSee('seluruh periode')
            ->assertSee('Pemasukan bulan lalu');
    }

    private function seedIncomeAndExpense(): void
    {
        Transaction::create([
            'type' => 'income',
            'category' => 'SPP / Pembayaran Kelas',
            'amount' => 500000,
            'transaction_date' => now()->startOfMonth()->toDateString(),
            'description' => 'Pemasukan bulan ini',
        ]);

        Transaction::create([
            'type' => 'expense',
            'category' => 'Pembelian Inventaris',
            'amount' => 200000,
            'transaction_date' => now()->startOfMonth()->toDateString(),
            'description' => 'Pengeluaran bulan ini',
        ]);
    }

    public function test_ringkasan_benar_tanpa_filter_tipe(): void
    {
        $this->seedIncomeAndExpense();

        $response = $this->actingAs($this->makeUser())->get(route('financials.index'))->assertOk();

        $response->assertViewHas('totalIncome', 500000.0);
        $response->assertViewHas('totalExpense', 200000.0);
        $response->assertViewHas('balance', 300000.0);
    }

    /**
     * Filter tipe hanya menyaring tabel. Sebelumnya filter ini ikut terpakai saat
     * menghitung kartu, sehingga kartu lawannya jadi 0 dan Saldo salah.
     */
    public function test_filter_tipe_tidak_merusak_ringkasan(): void
    {
        $this->seedIncomeAndExpense();
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('financials.index', ['type' => 'income']))->assertOk();
        $response->assertViewHas('totalIncome', 500000.0);
        $response->assertViewHas('totalExpense', 200000.0);
        $response->assertViewHas('balance', 300000.0); // bukan 500.000
        // Tabelnya sendiri tetap tersaring.
        $response->assertSee('Pemasukan bulan ini')->assertDontSee('Pengeluaran bulan ini');

        $response = $this->actingAs($user)->get(route('financials.index', ['type' => 'expense']))->assertOk();
        $response->assertViewHas('totalIncome', 500000.0);
        $response->assertViewHas('totalExpense', 200000.0);
        $response->assertViewHas('balance', 300000.0); // bukan -200.000
        $response->assertSee('Pengeluaran bulan ini')->assertDontSee('Pemasukan bulan ini');
    }

    public function test_pencarian_tetap_mempersempit_ringkasan(): void
    {
        $this->seedIncomeAndExpense();

        // Pencarian menyaring cakupan: hanya baris pengeluaran yang cocok.
        $response = $this->actingAs($this->makeUser())
            ->get(route('financials.index', ['search' => 'Inventaris']))
            ->assertOk();

        $response->assertViewHas('totalIncome', 0.0);
        $response->assertViewHas('totalExpense', 200000.0);
        $response->assertViewHas('balance', -200000.0);
    }

    public function test_export_default_mencakup_seluruh_periode(): void
    {
        $this->seedTransactions();

        $csv = $this->actingAs($this->makeUser())
            ->get(route('export.financials', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Pemasukan bulan ini', $csv);
        $this->assertStringContainsString('Pemasukan bulan lalu', $csv);
    }

    public function test_export_mengikuti_filter_bulan_yang_dipilih(): void
    {
        $this->seedTransactions();

        $csv = $this->actingAs($this->makeUser())
            ->get(route('export.financials', ['month' => now()->format('Y-m'), 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Pemasukan bulan ini', $csv);
        $this->assertStringNotContainsString('Pemasukan bulan lalu', $csv);
    }
}
