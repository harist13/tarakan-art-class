<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Holiday Class (kelas musiman/event, berdiri sendiri dari Class Management reguler)
     */
    public function up(): void
    {
        Schema::create('holiday_classes', function (Blueprint $table) {
            $table->id();
            $table->string('class_name');
            $table->dateTime('schedule');
            $table->unsignedInteger('capacity');
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_classes');
    }
};