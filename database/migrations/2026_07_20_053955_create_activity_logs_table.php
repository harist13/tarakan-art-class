<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Activity Log (F15) — Phase 3
     * Relasi: belongsTo User. subject_type/subject_id = polymorphic ke model manapun (Student, Payment, dll).
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action'); // created, updated, deleted
            $table->string('subject_type')->nullable(); // App\Models\Student, dll
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};