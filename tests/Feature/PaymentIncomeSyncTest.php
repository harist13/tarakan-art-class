<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\StudentObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: sinkronisasi Pembayaran (F6) → Laporan Keuangan (F7).
 * Invoice berstatus "paid" wajib muncul sebagai pemasukan, dan hilang lagi
 * saat statusnya dibatalkan atau invoicenya di-void.
 */
class PaymentIncomeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'super_admin'): User
    {
        Role::firstOrCreate(['name' => $role]);
        $user = User::create([
            'full_name' => 'Admin QA',
            'email' => $role.'@example.com',
            'username' => $role,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'student_id' => 'STD001',
            'name' => 'Budi Santoso',
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => '081234567890',
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => now()->toDateString(),
        ]);
    }

    private function payload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'payment_amount' => 250000,
            'payment_method' => 'transfer',
            'payment_status' => 'paid',
            'notes' => null,
        ], $overrides);
    }

    public function test_pembayaran_paid_otomatis_jadi_pemasukan(): void
    {
        $user = $this->makeUser();
        $student = $this->makeStudent();

        $this->actingAs($user)
            ->post(route('payments.store'), $this->payload($student))
            ->assertRedirect(route('payments.index'));

        $payment = Payment::firstOrFail();

        $this->assertDatabaseHas('transactions', [
            'payment_id' => $payment->id,
            'type' => 'income',
            'amount' => 250000,
        ]);

        // Muncul di tabel menu Laporan Keuangan.
        $this->actingAs($user)
            ->get(route('financials.index'))
            ->assertOk()
            ->assertSee("Pembayaran {$payment->invoice_number} - Budi Santoso");
    }

    public function test_pembayaran_unpaid_tidak_dicatat_lalu_muncul_saat_dikonfirmasi_lunas(): void
    {
        $user = $this->makeUser();
        $student = $this->makeStudent();

        // Cash: satu-satunya metode yang boleh dilunaskan lewat tombol "Lunas".
        $this->actingAs($user)->post(route('payments.store'), $this->payload($student, [
            'payment_status' => 'unpaid',
            'payment_method' => 'cash',
        ]));

        $payment = Payment::firstOrFail();
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($user)
            ->patch(route('payments.confirm', $payment))
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'payment_id' => $payment->id,
            'type' => 'income',
        ]);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_revisi_nominal_invoice_ikut_memperbarui_pemasukan(): void
    {
        $user = $this->makeUser();
        $student = $this->makeStudent();

        $this->actingAs($user)->post(route('payments.store'), $this->payload($student));
        $payment = Payment::firstOrFail();

        $this->actingAs($user)->put(route('payments.update', $payment), $this->payload($student, [
            'payment_amount' => 400000,
        ]));

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'payment_id' => $payment->id,
            'amount' => 400000,
        ]);
    }

    public function test_status_kembali_unpaid_menghapus_pemasukan(): void
    {
        $user = $this->makeUser();
        $student = $this->makeStudent();

        $this->actingAs($user)->post(route('payments.store'), $this->payload($student));
        $payment = Payment::firstOrFail();

        $this->actingAs($user)->put(route('payments.update', $payment), $this->payload($student, [
            'payment_status' => 'unpaid',
        ]));

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_void_invoice_menghapus_pemasukan(): void
    {
        $user = $this->makeUser();
        $student = $this->makeStudent();

        $this->actingAs($user)->post(route('payments.store'), $this->payload($student));
        $payment = Payment::firstOrFail();

        $this->actingAs($user)->delete(route('payments.destroy', $payment));

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_hapus_murid_mempertahankan_pemasukan_tapi_melepasnya_dari_invoice(): void
    {
        $user = $this->makeUser();
        $student = $this->makeStudent();

        $this->actingAs($user)->post(route('payments.store'), $this->payload($student));
        $payment = Payment::firstOrFail();
        $invoice = $payment->invoice_number;

        $this->actingAs($user)
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        // Invoice ikut terhapus, tapi uang yang sudah masuk tetap di laporan.
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('transactions', 1);

        $transaction = Transaction::firstOrFail();
        $this->assertNull($transaction->payment_id);
        $this->assertSame(
            "Pembayaran {$invoice} - Budi Santoso ".StudentObserver::DETACHED_NOTE,
            $transaction->description
        );

        // Karena sudah lepas dari invoice, sekarang boleh dihapus manual.
        $this->actingAs($user)
            ->delete(route('financials.destroy', $transaction))
            ->assertRedirect(route('financials.index'));

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_hapus_murid_dengan_invoice_belum_lunas_tidak_menyisakan_transaksi(): void
    {
        $user = $this->makeUser();
        $student = $this->makeStudent();

        $this->actingAs($user)->post(route('payments.store'), $this->payload($student, [
            'payment_status' => 'unpaid',
        ]));

        $this->actingAs($user)->delete(route('students.destroy', $student));

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transaksi_otomatis_tidak_bisa_dihapus_dari_laporan_keuangan(): void
    {
        $user = $this->makeUser();
        $student = $this->makeStudent();

        $this->actingAs($user)->post(route('payments.store'), $this->payload($student));
        $transaction = Transaction::firstOrFail();

        $this->actingAs($user)
            ->delete(route('financials.destroy', $transaction))
            ->assertRedirect(route('financials.index'));

        $this->assertDatabaseCount('transactions', 1);
    }
}
