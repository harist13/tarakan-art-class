<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Scheduler (F4)
     * Relasi: belongsTo Student, belongsTo Class (kelas asal / current schedule)
     */
    public function up(): void
    {
        Schema::create('replacement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete(); // current schedule (class asal)
            $table->date('replacement_date');
            $table->time('replacement_time');
            $table->text('reason')->nullable();
            $table->enum('request_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); // Super Admin yang approve
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacement_requests');
    }
};