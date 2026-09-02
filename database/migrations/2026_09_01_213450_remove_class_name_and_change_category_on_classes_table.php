<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus kolom class_name dan ubah class_category dari enum menjadi string.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('class_name');
        });

        // Enum → string harus dilakukan di statement terpisah (SQLite compatibility).
        Schema::table('classes', function (Blueprint $table) {
            $table->string('class_category', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('class_name')->after('class_code');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->enum('class_category', ['preschool', 'coloring', 'drawing'])->change();
        });
    }
};
