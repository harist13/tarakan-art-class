<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periode tagihan (YYYY-MM) — "invoice ini untuk bulan apa".
     *
     * Sebelum ini tabel payments tidak punya konsep bulan tagihan; yang ada
     * hanya payment_date, yaitu kapan invoice DITERBITKAN. Keduanya sering
     * berbeda: invoice SPP September bisa saja terbit 28 Agustus. Akibatnya
     * sistem tidak bisa menjawab "murid X sudah ditagih untuk Agustus belum?",
     * dan admin bisa membuat dua invoice bulan yang sama untuk murid yang sama
     * tanpa satu pun peringatan.
     *
     * Unique (student_id, billing_period) yang menutup celah itu — dijaga
     * database, bukan kedisiplinan admin. Kolomnya nullable, dan MySQL
     * mengizinkan NULL berulang di unique index, jadi tagihan lepas di luar SPP
     * (biaya pendaftaran, pembelian alat) tetap bisa dibuat berkali-kali dalam
     * bulan yang sama.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('billing_period', 7)->nullable()->after('due_date');
        });

        // Data lama: periodenya disimpulkan dari bulan invoice diterbitkan.
        // Bila satu murid ternyata sudah punya >1 invoice di bulan yang sama,
        // hanya yang tertua yang memegang periode itu — sisanya dibiarkan NULL
        // dan terbaca sebagai tagihan lepas. Dengan begitu unique index di
        // bawah bisa dipasang tanpa membuang atau menggabungkan satu baris pun.
        $taken = [];

        DB::table('payments')
            ->select('id', 'student_id', 'payment_date')
            ->orderBy('id')
            ->each(function ($row) use (&$taken) {
                $period = substr((string) $row->payment_date, 0, 7);
                $key = $row->student_id.'|'.$period;

                if (isset($taken[$key])) {
                    return;
                }

                $taken[$key] = true;

                DB::table('payments')->where('id', $row->id)->update(['billing_period' => $period]);
            });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['student_id', 'billing_period'], 'payments_student_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_student_period_unique');
            $table->dropColumn('billing_period');
        });
    }
};
