<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detail tambahan dari form Kontak: tanggal lahir, tipe kelas, dan alamat.
     *
     * Nama kolom sengaja disamakan dengan tabel `students` supaya lead yang
     * jadi mendaftar bisa dipindahkan admin tanpa penerjemahan field.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('child_age');
            // Bukan enum: lead masih calon, tipe kelas boleh kosong / berubah.
            $table->string('class_type')->nullable()->after('date_of_birth');
            $table->text('address')->nullable()->after('parent_email');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'class_type', 'address']);
        });
    }
};
