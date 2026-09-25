<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentBundle;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\User;
use App\Support\GuardianGroups;
use App\Support\InvoiceWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: tagihan gabungan — invoice kakak-adik dibayar sekali lewat satu tautan.
 */
class PaymentBundleTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-TEST';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'midtrans.server_key' => self::SERVER_KEY,
            'midtrans.client_key' => 'SB-Mid-client-TEST',
            'midtrans.is_production' => false,
        ]);
    }

    private function admin(): User
    {
        if ($existing = User::where('username', 'admin')->first()) {
            return $existing;
        }

        Role::firstOrCreate(['name' => 'admin']);
        $user = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.com', 'username' => 'admin',
            'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function student(string $name, string $parent, string $phone): Student
    {
        return Student::create([
            'name' => $name, 'date_of_birth' => '2018-01-01', 'parent_name' => $parent,
            'phone_number' => $phone, 'class_type' => 'drawing', 'status' => 'active',
            'join_date' => now()->subYear()->toDateString(),
        ]);
    }

    private function invoice(Student $student, int $amount): Payment
    {
        return Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'billing_period' => Payment::periodFor(),
            'payment_amount' => $amount,
            'payment_method' => 'transfer',
            'payment_status' => 'unpaid',
        ]);
    }

    private function fakeSnap(): void
    {
        Http::fake([
            '*/snap/v1/transactions' => Http::sequence()
                ->push(['token' => 'snap-1', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/snap-1'])
                ->push(['token' => 'snap-2', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/snap-2']),
        ]);
    }

    private function notify(PaymentBundle $bundle, string $status = 'settlement')
    {
        $gross = $bundle->snap_amount.'.00';

        return $this->postJson(route('midtrans.notification'), [
            'order_id' => $bundle->snap_order_id,
            'status_code' => '200',
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'trx-123',
            'signature_key' => hash('sha512', $bundle->snap_order_id.'200'.$gross.self::SERVER_KEY),
        ]);
    }

    /** Dua anak senomor → satu keluarga, satu gabungan berisi dua invoice. */
    private function siblingsBundle(): array
    {
        $alice = $this->student('Alice', 'Desi', '081234567890');
        $tiffany = $this->student('Tiffany', 'Ibu Desi', '0812-3456-7890');
        $a = $this->invoice($alice, 360000);
        $t = $this->invoice($tiffany, 300000);

        $this->actingAs($this->admin())
            ->post(route('payment-bundles.store'), ['payment_ids' => [$a->id, $t->id]])
            ->assertSessionHasNoErrors();

        return [PaymentBundle::sole(), $a, $t];
    }

    public function test_keluarga_dikenali_dari_nomor_atau_nama_wali(): void
    {
        // Senomor.
        $this->invoice($this->student('Alice', 'Desi', '081111111111'), 100);
        $this->invoice($this->student('Tiffany', 'Bu Desi', '081111111111'), 100);
        // Nomor beda, nama wali sama (setelah sapaan dibuang).
        $this->invoice($this->student('Joyceline', 'Christine', '082222222222'), 100);
        $this->invoice($this->student('Kayson', 'Ibu Christine', '083333333333'), 100);
        // Tidak berhubungan.
        $this->invoice($this->student('Lain', 'Orang Lain', '084444444444'), 100);

        $groups = GuardianGroups::unpaid()->keyBy(fn ($g) => $g['students']->pluck('name')->implode(','));

        $this->assertEqualsCanonicalizing(['Alice,Tiffany', 'Joyceline,Kayson'], $groups->keys()->all());
        $this->assertFalse($groups['Alice,Tiffany']['name_only']);
        $this->assertTrue($groups['Joyceline,Kayson']['name_only']);
    }

    public function test_invoice_beda_keluarga_tidak_bisa_digabung(): void
    {
        $a = $this->invoice($this->student('Alice', 'Desi', '081111111111'), 100);
        $this->invoice($this->student('Tiffany', 'Desi', '081111111111'), 100);
        $lain = $this->invoice($this->student('Lain', 'Orang Lain', '084444444444'), 100);
        $this->invoice($this->student('Lain Adik', 'Orang Lain', '084444444444'), 100);

        $this->actingAs($this->admin())
            ->post(route('payment-bundles.store'), ['payment_ids' => [$a->id, $lain->id]])
            ->assertSessionHasErrors('payment_ids');

        $this->actingAs($this->admin())
            ->post(route('payment-bundles.store'), ['payment_ids' => [$a->id]])
            ->assertSessionHasErrors('payment_ids');

        $this->assertSame(0, PaymentBundle::count());
    }

    public function test_satu_tautan_satu_transaksi_untuk_total_semua_invoice(): void
    {
        $this->fakeSnap();
        [$bundle] = $this->siblingsBundle();

        $this->get($bundle->payUrl())
            ->assertOk()
            ->assertSee('Alice')->assertSee('Tiffany')
            ->assertSee('Rp 660.000');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/snap/v1/transactions')
            && $r['transaction_details']['gross_amount'] === 660000
            && count($r['item_details']) === 2);

        $this->assertSame(660000, $bundle->fresh()->snap_amount);
    }

    public function test_pelunasan_menandai_semua_invoice_lunas_dan_tercatat_di_keuangan(): void
    {
        $this->fakeSnap();
        [$bundle, $a, $t] = $this->siblingsBundle();
        $this->get($bundle->payUrl());

        $this->notify($bundle->fresh())->assertOk()->assertJson(['message' => 'Settled']);

        $this->assertSame('paid', $a->fresh()->payment_status);
        $this->assertSame('paid', $t->fresh()->payment_status);
        $this->assertSame('virtual_account', $a->fresh()->payment_method);
        $this->assertNotNull($bundle->fresh()->paid_at);
        // Pemasukan tetap tercatat per invoice.
        $this->assertSame(2, Transaction::whereIn('payment_id', [$a->id, $t->id])->count());

        $this->get($bundle->payUrl())->assertOk()->assertSee('Pembayaran diterima');
    }

    public function test_invoice_yang_dibayar_terpisah_tidak_ditagih_lagi(): void
    {
        $this->fakeSnap();
        [$bundle, $a, $t] = $this->siblingsBundle();
        $this->get($bundle->payUrl());
        $this->assertSame(660000, $bundle->fresh()->snap_amount);

        // Invoice Tiffany dilunasi tunai di studio.
        $t->forceFill(['payment_status' => 'paid', 'payment_method' => 'cash'])->save();

        $this->get($bundle->payUrl())->assertOk()->assertSee('Rp 360.000')->assertDontSee('Rp 660.000');

        // Total berubah → transaksi Snap baru dengan order_id berikutnya.
        $fresh = $bundle->fresh();
        $this->assertSame(360000, $fresh->snap_amount);
        $this->assertSame('snap-2', $fresh->snap_token);
        $this->assertStringEndsWith('-2', $fresh->snap_order_id);
    }

    public function test_gabungan_dibatalkan_tidak_menerbitkan_transaksi_tapi_va_lamanya_tetap_dilunasi(): void
    {
        $this->fakeSnap();
        [$bundle, $a, $t] = $this->siblingsBundle();
        $this->get($bundle->payUrl());

        $this->actingAs($this->admin())->delete(route('payment-bundles.destroy', $bundle))->assertSessionHasNoErrors();
        $this->assertNotNull($bundle->fresh()->cancelled_at);

        $this->get($bundle->payUrl())->assertOk()->assertSee('sudah tidak berlaku');
        Http::assertSentCount(1);

        // Orang tua tetap membayar VA yang sudah dipegangnya.
        $this->notify($bundle->fresh())->assertOk();
        $this->assertSame('paid', $a->fresh()->payment_status);
        $this->assertSame('paid', $t->fresh()->payment_status);
    }

    public function test_menggabung_ulang_membatalkan_gabungan_lama(): void
    {
        [$lama, $a, $t] = $this->siblingsBundle();

        $this->post(route('payment-bundles.store'), ['payment_ids' => [$a->id, $t->id]])->assertSessionHasNoErrors();

        $this->assertNotNull($lama->fresh()->cancelled_at);
        $baru = PaymentBundle::whereNull('cancelled_at')->sole();
        $this->assertSame($baru->id, $a->fresh()->load('bundles')->openBundle()->id);
    }

    public function test_pesan_whatsapp_memuat_rincian_per_anak_dan_total(): void
    {
        [$bundle] = $this->siblingsBundle();
        $bundle->load('payments.student');

        $pesan = InvoiceWhatsApp::bundleMessage($bundle, $bundle->payUrl());

        $this->assertStringStartsWith('Hai wali murid Alice & Tiffany, apa kabar?', $pesan);
        $this->assertStringContainsString("1. No. Invoice: ", $pesan);
        $this->assertStringContainsString("2. No. Invoice: ", $pesan);
        $this->assertStringContainsString('Total: Rp 660.000', $pesan);
        $this->assertStringContainsString($bundle->payUrl(), $pesan);
        $this->assertStringEndsWith('Terima kasih banyak😊🙏🏻', $pesan);

        $this->get(route('payment-bundles.whatsapp', $bundle))
            ->assertRedirectContains('https://api.whatsapp.com/send?phone=6281234567890');
    }

    public function test_halaman_admin_menampilkan_keluarga_dan_gabungan(): void
    {
        [$bundle] = $this->siblingsBundle();

        $this->get(route('payment-bundles.index'))
            ->assertOk()
            ->assertSee($bundle->code)
            ->assertSee('Alice & Tiffany');

        $this->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Tagihan gabungan')
            ->assertSee($bundle->code);
    }

    public function test_kode_gabungan_mudah_dibaca(): void
    {
        [$bundle] = $this->siblingsBundle();

        $this->assertSame('Gabungan-001', $bundle->code);
    }

    public function test_invoice_gabungan_tampil_sebagai_satu_baris_di_daftar_pembayaran(): void
    {
        [$bundle, $a, $t] = $this->siblingsBundle();
        $lain = $this->invoice($this->student('Murid Lain', 'Wali Lain', '089999999999'), 100);

        $halaman = $this->get(route('payments.index'))->assertOk()
            ->assertSee($bundle->code)
            ->assertSee('Rp 660.000')
            ->assertSee($lain->invoice_number)
            ->getContent();

        // Invoice di dalam gabungan tidak lagi punya baris sendiri: penandanya
        // hanya dua (pemantau di baris tabel & di kartu gabungan), bukan empat.
        $this->assertSame(2, substr_count($halaman, 'data-payment-invoice="'.$a->invoice_number.'"'));
        $this->assertSame(2, substr_count($halaman, 'data-copy-link="'.$bundle->payUrl().'"'));

        // Mencari satu anak memunculkan baris gabungannya (berisi saudaranya).
        $this->get(route('payments.index', ['search' => 'Alice']))
            ->assertOk()
            ->assertSee($bundle->code)
            ->assertSee('Tiffany')
            ->assertDontSee($lain->invoice_number);

        // Setelah dibatalkan, keduanya kembali tampil sendiri-sendiri.
        $this->delete(route('payment-bundles.destroy', $bundle));
        $this->get(route('payments.index'))->assertOk()
            ->assertDontSee('data-copy-link="'.$bundle->payUrl().'"', false)
            ->assertSee('data-payment-invoice="'.$a->invoice_number.'"', false)
            ->assertSee('data-payment-invoice="'.$t->invoice_number.'"', false);
    }

    public function test_filter_tagihan_gabungan_menampilkan_jumlah_dan_hanya_barisnya(): void
    {
        [$bundle] = $this->siblingsBundle();
        $lain = $this->invoice($this->student('Murid Lain', 'Wali Lain', '089999999999'), 100);

        $this->get(route('payments.index'))
            ->assertSee('Tagihan gabungan (1)');

        $this->get(route('payments.index', ['status' => 'bundle']))
            ->assertOk()
            ->assertSee('data-copy-link="'.$bundle->payUrl().'"', false)
            ->assertDontSee($lain->invoice_number);
    }

    public function test_filter_status_tetap_berlaku_saat_mencari(): void
    {
        [, $a, $t] = $this->siblingsBundle();
        $t->forceFill(['payment_status' => 'paid', 'payment_method' => 'cash'])->save();

        $this->get(route('payments.index', ['search' => 'Tiffany', 'status' => 'unpaid']))
            ->assertOk()
            ->assertDontSee($t->invoice_number);
    }
}
