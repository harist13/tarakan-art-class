<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Student Report (F8) + Guest Report Access (F9)
     * Relasi: belongsTo Student
     */
    public function up(): void
    {
        Schema::create('student_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->text('activity_notes');
            $table->unsignedTinyInteger('achievement_score'); // 0-100
            $table->text('tutor_notes')->nullable();
            $table->string('credential_key')->unique(); // auto-generate: TAC-2026-001, dipakai untuk Guest Report Access
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_reports');
    }
};