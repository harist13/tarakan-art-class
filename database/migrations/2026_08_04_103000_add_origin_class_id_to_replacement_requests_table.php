<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pisahkan kelas asal (jadwal yang ditinggalkan) dari kelas tujuan.
     * `class_id` tetap dipakai sebagai kelas baru / slot pengganti.
     */
    public function up(): void
    {
        Schema::table('replacement_requests', function (Blueprint $table) {
            $table->foreignId('origin_class_id')->nullable()->after('student_id')
                ->constrained('classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('replacement_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_class_id');
        });
    }
};
