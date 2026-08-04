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

    public function test_default_hanya_menampilkan_bulan_berjalan(): void
    {
        $this->seedTransactions();

        $this->actingAs($this->makeUser())
            ->get(route('financials.index'))
            ->assertOk()
            ->assertSee('Pemasukan bulan ini')
            ->assertDontSee('Pemasukan bulan lalu')
            ->assertSee(now()->translatedFormat('F Y'))
            ->assertSee('Rp 200.000');
    }

    public function test_semua_bulan_menampilkan_seluruh_periode(): void
    {
        $this->seedTransactions();

        $this->actingAs($this->makeUser())
            ->get(route('financials.index', ['month' => '']))
            ->assertOk()
            ->assertSee('Pemasukan bulan ini')
            ->assertSee('Pemasukan bulan lalu')
            ->assertSee('Semua Periode')
            // Total ikut seluruh periode: 200.000 + 350.000.
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
            ->assertSee('Semua Periode')
            ->assertSee('Pemasukan bulan lalu');
    }

    public function test_export_mengikuti_filter_semua_bulan(): void
    {
        $this->seedTransactions();

        $csv = $this->actingAs($this->makeUser())
            ->get(route('export.financials', ['month' => '', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Pemasukan bulan ini', $csv);
        $this->assertStringContainsString('Pemasukan bulan lalu', $csv);
    }
}
