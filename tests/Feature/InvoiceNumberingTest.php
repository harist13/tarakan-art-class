<?php

namespace Tests\Feature;

use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA: penomoran invoice.
 *
 * Nomor yang sudah terpakai tidak boleh kembali walau invoicenya dihapus —
 * riwayat pembukuan jadi rancu, dan Midtrans menolak order_id yang berulang.
 */
class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Budi Santoso',
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => '081234567890',
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => now()->toDateString(),
        ]);
    }

    private function makePayment(Student $student): Payment
    {
        return Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'payment_amount' => 250000,
            'payment_method' => 'transfer',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_nomor_invoice_berurutan(): void
    {
        $student = $this->makeStudent();

        $this->assertSame('INV001', $this->makePayment($student)->invoice_number);
        $this->assertSame('INV002', $this->makePayment($student)->invoice_number);
        $this->assertSame('INV003', $this->makePayment($student)->invoice_number);
    }

    public function test_nomor_invoice_tidak_dipakai_ulang_setelah_invoice_dihapus(): void
    {
        $student = $this->makeStudent();

        $this->makePayment($student);
        $kedua = $this->makePayment($student);
        $this->assertSame('INV002', $kedua->invoice_number);

        // Invoice terbaru di-void; dulu ini membuat nomornya terbit lagi.
        $kedua->delete();

        $this->assertSame('INV003', $this->makePayment($student)->invoice_number);
    }

    public function test_pencacah_maju_walau_seluruh_invoice_dihapus(): void
    {
        $student = $this->makeStudent();

        $this->makePayment($student);
        $this->makePayment($student);
        Payment::query()->delete();

        $this->assertSame('INV003', $this->makePayment($student)->invoice_number);
    }

    public function test_pencacah_menyimpan_posisi_terakhir(): void
    {
        $student = $this->makeStudent();
        $this->makePayment($student);

        $this->assertSame(2, NumberSequence::where('name', 'invoice')->value('next_number'));
    }
}
