<?php

namespace App\Rules;

use App\Models\Student;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Menolak murid yang pembayarannya belum lunas untuk dipakai di modul akademik
 * (absensi, raport, replacement class). Definisi "lunas" ada di Student::scopePaid().
 *
 * Dipakai bersama rule `exists:students,id`; id yang tidak ada dibiarkan
 * agar pesan errornya tidak dobel.
 */
class StudentPaymentSettled implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $student = Student::find($value);

        if (! $student || $student->isPaid()) {
            return;
        }

        $fail("Murid {$student->name} belum bisa diproses karena pembayarannya {$student->paymentBlockReason()}. Lunasi invoice-nya lebih dulu di menu Pembayaran.");
    }
}
