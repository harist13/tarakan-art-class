<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pencacah nomor dokumen (lihat App\Models\NumberSequence).
     *
     * Dibuat agar nomor invoice tidak pernah terpakai ulang setelah invoice
     * lama dihapus — penyebab tabrakan order_id di Midtrans dan riwayat
     * pembukuan yang rancu.
     */
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        // Mulai dari nomor tertinggi yang PERNAH ada + 1, supaya pencacah tidak
        // menabrak invoice yang sudah terbit. Nomor invoice berformat INV012,
        // jadi angkanya diambil dengan membuang semua karakter non-digit.
        $highest = DB::table('payments')->pluck('invoice_number')
            ->map(fn ($number) => (int) preg_replace('/\D/', '', (string) $number))
            ->max() ?? 0;

        DB::table('number_sequences')->insert([
            'name' => 'invoice',
            'next_number' => $highest + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
