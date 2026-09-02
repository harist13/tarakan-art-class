<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pekan mulai ternyata sifat murid, bukan sifat kelas.
     *
     * Versi pertama menaruh `start_week` di tabel classes, dan itu keliru: satu
     * kelas dimasuki banyak anak yang mendaftar di pekan berbeda-beda, sehingga
     * satu angka per kelas tak bisa menjawab "Budi masuk pekan ke-3, bayarnya
     * berapa". Karena itu kolomnya pindah ke pivot `student_class` — tempat
     * hubungan murid ↔ kelas memang dicatat.
     *
     * Yang tinggal di kelas adalah daftar harganya: `start_week_fees`, berbentuk
     * {"1": 450000, "2": 350000, …}. Harga tetap per pekan, bukan prorata
     * terhitung — jadi angkanya diketik admin, bukan diturunkan dari class_fee.
     * Pekan yang dikosongkan jatuh kembali ke class_fee.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('start_week');
            $table->json('start_week_fees')->nullable()->after('registration_fee');
        });

        Schema::table('student_class', function (Blueprint $table) {
            // Nullable: pendaftaran lama tidak punya catatan pekannya, dan menebak
            // angka untuk data itu lebih buruk daripada mengakui tidak tahu —
            // Student::startWeek() menurunkannya dari tanggal daftar bila kosong.
            $table->unsignedTinyInteger('start_week')->nullable()->after('enrolled_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_class', function (Blueprint $table) {
            $table->dropColumn('start_week');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('start_week_fees');
            $table->unsignedTinyInteger('start_week')->default(1)->after('class_type');
        });
    }
};
