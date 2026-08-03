<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acara / agenda umum yang tampil di kalender (mis. rapat, pameran, kegiatan),
     * terpisah dari jadwal kelas & hari libur. Jam kosong = acara seharian.
     */
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('description')->nullable();
            $table->string('color', 7)->nullable(); // hex, mis. #6366F1
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
