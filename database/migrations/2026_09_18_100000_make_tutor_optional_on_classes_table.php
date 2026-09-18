<?php

use App\Models\Tutor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas boleh belum punya tutor.
 *
 * Sebelum ini `tutor_id` wajib diisi, padahal kenyataan sanggar tidak selalu
 * begitu: kelas hasil impor spreadsheet berjalan lebih dulu, tutornya ditunjuk
 * admin belakangan. Kekosongan itu selama ini diwakili baris tutor bernama
 * "Belum Ditentukan" — nama yang harus dikenali satu per satu oleh tiap layar
 * yang menulis nama tutor, dan sempat terbaca orang tua di jadwal publik
 * sebagai nama pengajar.
 *
 * Sesudah migrasi ini, "belum ditentukan" jadi keadaan (NULL), bukan nama.
 * Baris titipannya dilepas dari kelas-kelasnya lalu dihapus.
 *
 * Kaitannya juga berubah: restrictOnDelete → nullOnDelete. Tutor yang berhenti
 * tidak lagi menyandera kelasnya — kelasnya kembali berstatus "Tutor kosong"
 * dan menunggu penggantinya. (Tombol hapus tutor di layar masih menolak tutor
 * yang sedang mengampu kelas; yang berubah hanya apa yang mungkin di basis
 * data, bukan alurnya.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $titipan = DB::table('tutors')->whereRaw('TRIM(name) LIKE ?', [Tutor::PLACEHOLDER_NAME])->pluck('id');

        // dropForeign + change dalam satu panggilan: SQLite membangun ulang
        // tabelnya sekali untuk keduanya, MySQL menjalankannya berurutan.
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['tutor_id']);
            $table->unsignedBigInteger('tutor_id')->nullable()->change();
            $table->foreign('tutor_id')->references('id')->on('tutors')->cascadeOnUpdate()->nullOnDelete();
        });

        if ($titipan->isNotEmpty()) {
            DB::table('classes')->whereIn('tutor_id', $titipan)->update(['tutor_id' => null]);
            DB::table('tutors')->whereIn('id', $titipan)->delete();
        }
    }

    public function down(): void
    {
        // Kolomnya tidak bisa kembali NOT NULL selama ada kelas tanpa tutor,
        // jadi baris titipannya dibuat ulang untuk menampung mereka — keadaan
        // persis sebelum migrasi ini.
        $kosong = DB::table('classes')->whereNull('tutor_id')->exists();

        if ($kosong) {
            $titipan = DB::table('tutors')->whereRaw('TRIM(name) LIKE ?', [Tutor::PLACEHOLDER_NAME])->value('id')
                ?? DB::table('tutors')->insertGetId([
                    'name' => Tutor::PLACEHOLDER_NAME,
                    'status' => Tutor::STATUS_PART_TIME,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('classes')->whereNull('tutor_id')->update(['tutor_id' => $titipan]);
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['tutor_id']);
            $table->unsignedBigInteger('tutor_id')->nullable(false)->change();
            $table->foreign('tutor_id')->references('id')->on('tutors')->cascadeOnUpdate()->restrictOnDelete();
        });
    }
};
