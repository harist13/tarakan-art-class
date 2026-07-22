<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot: Student <-> Class (many-to-many)
     * Dipakai sebagai acuan untuk Attendance & Scheduler (student aktif di kelas mana).
     */
    public function up(): void
    {
        Schema::create('student_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('enrolled_at');
            $table->timestamps();

            $table->unique(['student_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class');
    }
};