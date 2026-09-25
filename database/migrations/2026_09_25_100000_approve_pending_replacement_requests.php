<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Replacement tidak lagi menunggu persetujuan Super Admin — yang diatur
     * admin langsung berlaku. Request yang masih menunggu saat aturan ini
     * berubah ikut diberlakukan; tanpa itu ia menggantung selamanya, karena
     * tombol setujui/tolak sudah tidak ada.
     *
     * Request yang dulu ditolak dibiarkan: itu keputusan yang sudah diambil.
     */
    public function up(): void
    {
        DB::table('replacement_requests')->where('request_status', 'pending')->update(['request_status' => 'approved']);
    }

    public function down(): void
    {
        // Tidak bisa dibalik: setelah diberlakukan, tidak ada penanda request
        // mana yang dulunya masih menunggu.
    }
};
