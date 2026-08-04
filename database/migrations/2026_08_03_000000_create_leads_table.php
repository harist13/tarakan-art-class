<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lead dari form Kontak website publik.
     *
     * Catatan PRD: "Pendaftaran Online Publik" masih out of scope — tabel ini
     * hanya menampung calon murid untuk ditindaklanjuti admin, bukan
     * pendaftaran self-service. Data final tetap masuk lewat modul Murid (F2).
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('child_name');
            $table->unsignedTinyInteger('child_age')->nullable();
            $table->string('parent_name');
            $table->string('parent_phone');
            $table->string('parent_email')->nullable();
            $table->string('program')->nullable();   // slug program yang diminati
            $table->text('message')->nullable();
            $table->enum('status', ['new', 'contacted', 'enrolled', 'rejected'])->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
