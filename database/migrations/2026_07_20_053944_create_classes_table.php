<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Class Management (F3)
     * Relasi: belongsTo Tutor
     */
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('class_code')->unique(); // auto-generate: CLS001
            $table->string('class_name');
            $table->enum('class_category', ['preschool', 'coloring', 'drawing']);
            $table->foreignId('tutor_id')->constrained('tutors')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('capacity');
            $table->date('schedule_date');
            $table->time('schedule_time');
            $table->decimal('class_fee', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};