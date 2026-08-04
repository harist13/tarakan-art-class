<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usia murid. Normalnya terisi otomatis dari date_of_birth, tapi admin boleh
     * menimpanya secara manual (mis. tanggal lahir hanya perkiraan). Nullable:
     * bila kosong, aksesor Student::getAgeAttribute() jatuh kembali ke hitungan otomatis.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('age');
        });
    }
};
