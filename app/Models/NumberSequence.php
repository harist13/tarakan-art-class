<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pencacah nomor dokumen yang tidak pernah mundur.
 *
 * Sebelumnya nomor invoice dihitung dari baris terakhir di tabel payments.
 * Cara itu punya dua cacat: menghapus invoice terbaru membuat nomornya
 * dipakai ulang oleh invoice berikutnya (bikin riwayat pembukuan rancu dan
 * ditolak Midtrans karena order_id wajib unik selamanya), dan dua admin yang
 * menyimpan invoice bersamaan bisa mendapat nomor yang sama.
 *
 * Di sini nomor diambil dari pencacah tersendiri: sekali terpakai, tidak
 * pernah kembali walau invoicenya dihapus.
 */
class NumberSequence extends Model
{
    protected $fillable = ['name', 'next_number'];

    protected $casts = ['next_number' => 'integer'];

    /**
     * Ambil nomor berikutnya lalu majukan pencacahnya.
     *
     * lockForUpdate menahan baris pencacah selama transaksi supaya dua proses
     * yang bersamaan tidak membaca nilai yang sama.
     */
    public static function next(string $name): int
    {
        return DB::transaction(function () use ($name) {
            $sequence = self::query()->lockForUpdate()->firstOrCreate(
                ['name' => $name],
                ['next_number' => 1]
            );

            $number = $sequence->next_number;
            $sequence->update(['next_number' => $number + 1]);

            return $number;
        });
    }
}
