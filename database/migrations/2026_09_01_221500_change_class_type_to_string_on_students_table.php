<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah kolom class_type pada tabel students dari enum menjadi string bebas
     * agar cocok dengan class_category pada tabel classes.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('class_type', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('class_type', ['preschool', 'coloring', 'drawing'])->change();
        });
    }
};
