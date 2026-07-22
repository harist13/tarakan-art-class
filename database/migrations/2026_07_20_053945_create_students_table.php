<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Student Management (F2)
     * "Class Type" (Preschool/Coloring/Drawing) sesuai PRD berupa kategori umum di level murid,
     * sedangkan kelas spesifik (dengan tutor & jadwal) dihubungkan lewat pivot student_class,
     * supaya satu murid bisa mengikuti >1 kelas sepanjang waktu (histori replacement dsb).
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->unique(); // auto-generate: STD001
            $table->string('name');
            $table->date('date_of_birth');
            $table->string('parent_name');
            $table->string('phone_number');
            $table->string('instagram_username')->nullable();
            $table->text('address')->nullable();
            $table->enum('class_type', ['preschool', 'coloring', 'drawing']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('join_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};