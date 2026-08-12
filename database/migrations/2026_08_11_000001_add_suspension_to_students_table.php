<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penangguhan murid karena tunggakan lewat masa toleransi.
     *
     * Sengaja kolom terpisah, bukan nilai baru di enum `status`, karena keduanya
     * beda pemilik: `status` adalah keputusan admin (murid berhenti les),
     * `suspended_at` diisi/dikosongkan otomatis oleh sistem mengikuti tagihan
     * (App\Console\Commands\SuspendOverdueStudents & PaymentObserver).
     * Dengan begitu murid yang melunasi kembali ke kondisi semula tanpa sistem
     * perlu menebak status admin sebelumnya.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->string('suspended_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspended_reason']);
        });
    }
};
