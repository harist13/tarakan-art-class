<?php

namespace App\Rules;

use App\Models\Student;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Menolak murid yang menunggak untuk hal-hal yang memang boleh ditahan sampai
 * tagihannya beres — kelas pengganti dan pindah kelas.
 *
 * Sengaja TIDAK dipakai di absensi: kehadiran adalah fakta yang sudah terjadi,
 * menolaknya hanya membuat catatan kelas bolong. Definisi "menunggak" ada di
 * Student::scopeInArrears() — invoice yang belum jatuh tempo tidak dihitung.
 *
 * Dipakai bersama rule `exists:students,id`; id yang tidak ada dibiarkan
 * agar pesan errornya tidak dobel.
 */
class StudentPaymentSettled implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $student = Student::find($value);

        if (! $student || ! $student->hasArrears()) {
            return;
        }

        $fail("Murid {$student->name} sedang {$student->paymentBlockReason()}. Lunasi tunggakannya lebih dulu di menu Pembayaran.");
    }
}
