<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanggal jatuh tempo invoice.
     *
     * Sebelum ini status `unpaid` tidak punya arti waktu: invoice SPP bulan depan
     * yang baru terbit dianggap sama beratnya dengan tunggakan 3 minggu, sehingga
     * murid yang rajin bayar ikut terkunci dari modul akademik setiap kali admin
     * menerbitkan invoice berikutnya. Dengan due_date, "menunggak" baru terhitung
     * setelah tanggal ini lewat (lihat Payment::scopeOverdue & config academic.php).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('payment_date');
        });

        // Data lama: anggap jatuh tempo = tanggal invoice, supaya perilakunya
        // tidak berubah mendadak untuk tunggakan yang memang sudah ada.
        DB::table('payments')->whereNull('due_date')->update([
            'due_date' => DB::raw('payment_date'),
        ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
