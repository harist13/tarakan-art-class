<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Authentication & User Management (F13, F14)
     * Role disimpan sebagai enum karena hanya ada 2 role (Super Admin, Admin).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'admin'])->default('admin');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('activation_key')->nullable(); // dipakai saat authentication (optional)
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};