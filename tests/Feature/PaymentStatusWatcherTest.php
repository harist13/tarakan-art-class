<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: pemantau pelunasan di halaman Daftar Transaksi Pembayaran.
 *
 * Pembayaran online dilunasi oleh notifikasi Midtrans yang tiba di server,
 * bukan oleh perbuatan admin di layar. Halaman menanyakan status tiap dua detik
 * lewat endpoint kecil ini, lalu memuat ulang dirinya begitu ada yang lunas.
 */
class PaymentStatusWatcherTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        if ($this->admin) {
            return $this->admin;
        }

        Role::firstOrCreate(['name' => 'admin']);
        $user = User::create([
            'full_name' => 'Admin User',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $this->admin = $user;
    }

    private function makeStudent(string $name = 'Murid A'): Student
    {
        return Student::create([
            'name' => $name,
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => '081234567890',
            'address' => 'Jl. Mawar 1',
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => '2026-01-15',
        ]);
    }

    private function invoice(string $status = 'unpaid', ?string $period = null): Payment
    {
        return Payment::create([
            'student_id' => $this->makeStudent('Murid '.uniqid())->id,
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'billing_period' => $period,
            'payment_amount' => 150000,
            'payment_method' => 'qris',
            'payment_status' => $status,
        ]);
    }

    // ─── ENDPOINT ──────────────────────────────────────────────────

    public function test_endpoint_returns_current_status_for_the_given_invoices(): void
    {
        $unpaid = $this->invoice('unpaid');
        $paid = $this->invoice('paid');

        $this->actingAs($this->admin())
            ->getJson(route('payments.statuses', ['ids' => $unpaid->id.','.$paid->id]))
            ->assertOk()
            ->assertExactJson(['statuses' => [
                (string) $unpaid->id => 'unpaid',
                (string) $paid->id => 'paid',
            ]]);
    }

    public function test_endpoint_reflects_a_settlement_that_happened_on_the_server(): void
    {
        $payment = $this->invoice('unpaid');

        // Persis yang dilakukan webhook Midtrans: status berubah tanpa admin
        // menyentuh layarnya sama sekali.
        $payment->update(['payment_status' => 'paid']);

        $this->actingAs($this->admin())
            ->getJson(route('payments.statuses', ['ids' => $payment->id]))
            ->assertOk()
            ->assertJsonPath('statuses.'.$payment->id, 'paid');
    }

    public function test_endpoint_ignores_junk_and_empty_input(): void
    {
        $this->invoice('unpaid');

        $this->actingAs($this->admin())
            ->getJson(route('payments.statuses'))
            ->assertOk()
            ->assertExactJson(['statuses' => []]);

        // Id yang bukan angka atau tidak ada tidak boleh membuat endpoint gagal.
        $this->actingAs($this->admin())
            ->getJson(route('payments.statuses', ['ids' => 'abc,,999999, ']))
            ->assertOk()
            ->assertExactJson(['statuses' => []]);
    }

    public function test_endpoint_requires_login(): void
    {
        $payment = $this->invoice('unpaid');

        // Aplikasi ini hanya membalas JSON untuk rute api/* (lihat
        // shouldRenderJsonWhen di bootstrap/app.php), jadi tamu diarahkan ke
        // login — bukan 401. Yang penting: statusnya tidak bocor tanpa login.
        // Pemantau di halaman mengenali balasan non-JSON dan berhenti sendiri.
        $this->getJson(route('payments.statuses', ['ids' => $payment->id]))
            ->assertRedirect(route('login'));
    }

    // ─── HALAMAN ───────────────────────────────────────────────────

    public function test_page_marks_rows_and_switches_the_watcher_on(): void
    {
        $unpaid = $this->invoice('unpaid');

        $html = $this->actingAs($this->admin())
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Memantau pembayaran')
            ->getContent();

        // Tiap baris membawa id & statusnya supaya pemantau tahu mana yang ditunggu.
        $this->assertStringContainsString('data-payment-id="'.$unpaid->id.'"', $html);
        $this->assertStringContainsString('data-payment-status="unpaid"', $html);
    }

    public function test_rows_carry_what_the_success_alert_needs_to_name(): void
    {
        $murid = $this->makeStudent('Doni Saputra');
        $payment = Payment::create([
            'student_id' => $murid->id,
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'payment_amount' => 150000,
            'payment_method' => 'qris',
            'payment_status' => 'unpaid',
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('payments.index'))
            ->assertOk()
            ->getContent();

        // Nomor invoice & nama murid dibaca dari baris sebelum halaman memuat
        // ulang — setelah reload tidak ada lagi yang tahu mana yang tadinya
        // masih Unpaid, jadi keduanya harus tersedia di DOM.
        $this->assertStringContainsString('data-payment-invoice="'.$payment->invoice_number.'"', $html);
        $this->assertStringContainsString('data-payment-student="Doni Saputra"', $html);

        // Wadah alertnya sudah ada sejak awal supaya pesannya muncul di tempat
        // yang sama dengan flash message biasa.
        $this->assertStringContainsString('id="paidAlert"', $html);
    }

    public function test_student_name_is_escaped_in_the_row_attribute(): void
    {
        $murid = $this->makeStudent('Budi "Si <b>Jago</b>" Santoso');
        Payment::create([
            'student_id' => $murid->id,
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'payment_amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('payments.index'))
            ->assertOk()
            ->getContent();

        // Nama murid diisi admin dan bisa berisi apa saja; ia tidak boleh
        // memutus atribut atau menyelundupkan markup ke dalam halaman.
        $this->assertStringNotContainsString('data-payment-student="Budi "', $html);
        $this->assertStringContainsString('&lt;b&gt;Jago&lt;/b&gt;', $html);
    }

    public function test_watcher_stays_off_when_nothing_is_pending(): void
    {
        $this->invoice('paid');

        // Tidak ada yang mungkin berubah, jadi tidak ada yang perlu ditanyakan
        // tiap dua detik.
        $this->actingAs($this->admin())
            ->get(route('payments.index'))
            ->assertOk()
            ->assertDontSee('Memantau pembayaran');
    }

    public function test_watcher_stays_off_when_the_filter_shows_only_paid(): void
    {
        $this->invoice('unpaid');
        $this->invoice('paid');

        // Pemantau menilai apa yang ada DI LAYAR, bukan seluruh tabel: menyaring
        // ke "Paid" berarti tidak ada satu pun baris yang ditunggu.
        $this->actingAs($this->admin())
            ->get(route('payments.index', ['status' => 'paid']))
            ->assertOk()
            ->assertDontSee('Memantau pembayaran');
    }
}
