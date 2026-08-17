<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: kirim invoice ke WhatsApp wali murid dari Daftar Transaksi Pembayaran.
 */
class PaymentWhatsappInvoiceTest extends TestCase
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

    private function makeStudent(string $phone = '081234567890'): Student
    {
        return Student::create([
            'name' => 'Budi Santoso',
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => $phone,
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => now()->toDateString(),
        ]);
    }

    private function makePayment(Student $student, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'payment_amount' => 250000,
            'payment_method' => 'transfer',
            'payment_status' => 'unpaid',
        ], $overrides));
    }

    public function test_nomor_wali_dinormalkan_ke_format_internasional(): void
    {
        $this->assertSame('6281234567890', $this->makeStudent('081234567890')->whatsappNumber());
        $this->assertSame('6281234567890', $this->makeStudent('+62 812-3456-7890')->whatsappNumber());
        $this->assertSame('6281234567890', $this->makeStudent('81234567890')->whatsappNumber());
        $this->assertNull($this->makeStudent('0812')->whatsappNumber());
    }

    public function test_tombol_whatsapp_mengarahkan_ke_chat_berisi_rincian_invoice(): void
    {
        $user = $this->makeUser();
        $payment = $this->makePayment($this->makeStudent());

        $response = $this->actingAs($user)->get(route('payments.whatsapp', $payment));

        $response->assertRedirectContains('https://wa.me/6281234567890');

        $target = urldecode($response->headers->get('Location'));
        $this->assertStringContainsString('Ibu Ani', $target);
        $this->assertStringContainsString($payment->invoice_number, $target);
        $this->assertStringContainsString('Rp 250.000', $target);
        $this->assertStringContainsString('Belum dibayar', $target);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'sent',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_invoice_lunas_dikirim_sebagai_tanda_terima(): void
    {
        $user = $this->makeUser();
        $payment = $this->makePayment($this->makeStudent(), ['payment_status' => 'paid']);

        $target = urldecode($this->actingAs($user)
            ->get(route('payments.whatsapp', $payment))
            ->headers->get('Location'));

        $this->assertStringContainsString('LUNAS', $target);
        $this->assertStringContainsString('sudah kami terima', $target);
    }

    public function test_nomor_tidak_valid_ditolak_dengan_pesan_kesalahan(): void
    {
        $user = $this->makeUser();
        $payment = $this->makePayment($this->makeStudent('0812'));

        $this->actingAs($user)
            ->get(route('payments.whatsapp', $payment))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_daftar_pembayaran_menampilkan_tombol_whatsapp(): void
    {
        $user = $this->makeUser();
        $payment = $this->makePayment($this->makeStudent());

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee(route('payments.whatsapp', $payment), false)
            ->assertSee('bi-whatsapp', false);
    }
}
