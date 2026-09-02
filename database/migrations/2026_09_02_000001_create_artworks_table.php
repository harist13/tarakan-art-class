<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Galeri Karya — foto karya murid, dikelompokkan per murid & bulan.
     *
     * Tidak ada tabel "folder": folder adalah pengelompokan (murid × bulan) yang
     * diturunkan dari `taken_on`. Dengan begitu tidak pernah ada folder kosong
     * yang menggantung, dan raport bulan tertentu selalu ketemu karyanya lewat
     * rentang periodenya.
     */
    public function up(): void
    {
        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('photo_path');
            $table->date('taken_on'); // tanggal karya dibuat — penentu folder bulannya
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Folder dibuka lewat pasangan ini, jadi diindeks bersama.
            $table->index(['student_id', 'taken_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artworks');
    }
};
