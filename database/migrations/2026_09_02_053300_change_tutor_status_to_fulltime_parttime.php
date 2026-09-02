<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah status tutor dari active/inactive menjadi full-time/part-time.
     *
     * Enum → string dulu (SQLite tidak mendukung ALTER ENUM), lalu data
     * yang ada dimigrasikan: active → full-time, inactive → part-time.
     */
    public function up(): void
    {
        // 1. Ubah kolom enum menjadi string agar bisa menampung nilai baru.
        Schema::table('tutors', function (Blueprint $table) {
            $table->string('status', 20)->default('full-time')->change();
        });

        // 2. Migrasikan data yang ada.
        DB::table('tutors')->where('status', 'active')->update(['status' => 'full-time']);
        DB::table('tutors')->where('status', 'inactive')->update(['status' => 'part-time']);
    }

    public function down(): void
    {
        // Kembalikan data ke nilai lama.
        DB::table('tutors')->where('status', 'full-time')->update(['status' => 'active']);
        DB::table('tutors')->where('status', 'part-time')->update(['status' => 'active']);

        Schema::table('tutors', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }
};
