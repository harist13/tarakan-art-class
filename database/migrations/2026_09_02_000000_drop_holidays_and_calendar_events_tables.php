<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur "Hari Libur" & "Acara / Agenda" dihapus.
     *
     * Migration pembuatnya ikut dihapus, jadi instalasi baru tidak pernah punya
     * tabel ini — dropIfExists di sini hanya membereskan instalasi lama yang
     * sudah terlanjur memilikinya.
     */
    public function up(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('calendar_events');
    }

    /**
     * Tidak dipulihkan: fiturnya dihapus permanen, bukan dipindahkan. Tabel
     * kosong tanpa model & controller tidak ada gunanya untuk di-rollback.
     */
    public function down(): void
    {
        //
    }
};
