<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alasan singkat saat admin menutup slot secara manual, agar admin lain
     * paham kenapa slot itu dicadangkan/ditiadakan. Kosong = ditutup tanpa catatan.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('closed_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('closed_reason');
        });
    }
};
