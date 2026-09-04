<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Nominal keuangan (Total Pendapatan di Dashboard dan ringkasan Laporan Keuangan)
 * hanya boleh terlihat oleh Super Admin. Admin biasa tetap bisa membuka kedua
 * halaman, tapi angkanya tidak dihitung dan tidak dikirim ke view.
 */
class FinanceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        Role::firstOrCreate(['name' => $role]);
        $user = User::create([
            'full_name' => ucfirst($role).' QA',
            'email' => $role.'@example.com',
            'username' => $role,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function seedTransactions(): void
    {
        Transaction::create([
            'type' => 'income',
            'category' => 'SPP / Pembayaran Kelas',
            'amount' => 1100000,
            'transaction_date' => now()->toDateString(),
        ]);

        Transaction::create([
            'type' => 'expense',
            'category' => 'Operasional',
            'amount' => 5750000,
            'transaction_date' => now()->toDateString(),
        ]);
    }

    public function test_super_admin_melihat_total_pendapatan_di_dashboard(): void
    {
        $this->seedTransactions();

        $response = $this->actingAs($this->makeUser('super_admin'))->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('canViewFinance', true);
        $response->assertSee('Total pendapatan');
        $response->assertSee('Rp 1.100.000');
    }

    public function test_admin_tidak_melihat_total_pendapatan_di_dashboard(): void
    {
        $this->seedTransactions();

        $response = $this->actingAs($this->makeUser('admin'))->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('canViewFinance', false);
        $response->assertViewHas('totalIncome', 0);
        $response->assertDontSee('Total pendapatan');
        $response->assertDontSee('Rp 1.100.000');
        // Scorecard lain tetap ada.
        $response->assertSee('Total murid');
        $response->assertSee('Total kelas');
    }

    public function test_super_admin_melihat_ringkasan_laporan_keuangan(): void
    {
        $this->seedTransactions();

        $response = $this->actingAs($this->makeUser('super_admin'))
            ->get(route('financials.index', ['month' => '']));

        $response->assertOk();
        $response->assertViewHas('canViewSummary', true);
        $response->assertSee('Saldo (Profit/Loss)');
        $response->assertSee('Rp 1.100.000');
        $response->assertSee('Rp -4.650.000');
    }

    public function test_admin_tidak_bisa_memilih_kategori_gaji_tutor(): void
    {
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)->get(route('financials.create'));

        $response->assertOk();
        $response->assertDontSee('Gaji Tutor');
        $response->assertSee('Perlengkapan');

        $this->actingAs($admin)
            ->post(route('financials.store'), [
                'type' => 'expense',
                'category' => 'Gaji Tutor',
                'amount' => 3000000,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('category');

        $this->assertDatabaseMissing('transactions', ['category' => 'Gaji Tutor']);
    }

    public function test_super_admin_tetap_bisa_memilih_kategori_gaji_tutor(): void
    {
        $response = $this->actingAs($this->makeUser('super_admin'))->get(route('financials.create'));

        $response->assertOk();
        $response->assertSee('Gaji Tutor');
    }

    public function test_admin_tidak_bisa_mengubah_atau_menghapus_transaksi_gaji_tutor(): void
    {
        $salary = Transaction::create([
            'type' => 'expense',
            'category' => 'Gaji Tutor',
            'amount' => 3000000,
            'transaction_date' => now()->toDateString(),
        ]);

        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get(route('financials.edit', $salary))
            ->assertRedirect(route('financials.index'));

        $this->actingAs($admin)->delete(route('financials.destroy', $salary))
            ->assertRedirect(route('financials.index'));

        $this->assertDatabaseHas('transactions', ['id' => $salary->id]);
    }

    public function test_admin_tidak_melihat_ringkasan_laporan_keuangan(): void
    {
        $this->seedTransactions();

        $response = $this->actingAs($this->makeUser('admin'))
            ->get(route('financials.index', ['month' => '']));

        $response->assertOk();
        $response->assertViewHas('canViewSummary', false);
        $response->assertViewHas('totalIncome', 0.0);
        $response->assertViewHas('balance', 0.0);
        $response->assertDontSee('Saldo (Profit/Loss)');
        $response->assertDontSee('Rp -4.650.000');
        // Rincian transaksi tetap bisa dibuka admin.
        $response->assertSee('Rincian transaksi');
    }
}
