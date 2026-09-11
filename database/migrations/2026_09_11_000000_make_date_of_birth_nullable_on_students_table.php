<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanggal lahir murid boleh kosong.
     *
     * Murid yang masuk lewat impor spreadsheet (students:import-csv) tidak membawa
     * tanggal lahir, dan mengisinya dengan tanggal karangan akan tampil sebagai usia
     * yang meyakinkan padahal salah. Form murid tetap mewajibkannya, jadi kolom ini
     * terisi begitu data muridnya disunting.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->change();
        });
    }

    /** Murid yang tanggal lahirnya masih kosong harus dilengkapi dulu sebelum rollback. */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable(false)->change();
        });
    }
};
