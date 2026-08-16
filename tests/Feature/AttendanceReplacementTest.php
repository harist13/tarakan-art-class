<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: daftar absen mengikuti replacement class.
 *
 * Kehadiran dicatat per sesi (kelas + tanggal), sementara replacement memindahkan
 * murid antar sesi. Jadi daftar absen satu sesi harus:
 *   - memuat murid yang pindah MASUK ke sesi ini,
 *   - mengeluarkan murid yang sesinya pindah KELUAR dari sini,
 *   - hanya menuruti request yang sudah di-approve.
 */
class AttendanceReplacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ClassRoom::flushHolidayCache();
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.com', 'username' => 'admin',
            'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function makeClass(string $name, int $dayOffset = 1): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak '.$name, 'status' => 'active']);

        return ClassRoom::create([
            'class_name' => $name,
            'class_category' => 'drawing',
            'tutor_id' => $tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->addDays($dayOffset)->toDateString(),
            'schedule_time' => '09:00',
            'class_fee' => 100000,
            'status' => 'open',
        ]);
    }

    private function makeStudent(string $name, ?ClassRoom $class = null): Student
    {
        $student = Student::create([
            'name' => $name, 'date_of_birth' => '2018-01-01', 'parent_name' => 'Wali '.$name,
            'phone_number' => '0812', 'class_type' => 'drawing', 'status' => 'active',
        ]);

        $class?->students()->attach($student->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);

        return $student;
    }

    private function replacement(array $attributes): ReplacementRequest
    {
        return ReplacementRequest::create(array_merge([
            'replacement_time' => '09:00',
            'request_status' => 'approved',
        ], $attributes));
    }

    /** Murid yang pindah masuk harus muncul di daftar absen kelas tujuan. */
    public function test_murid_pengganti_muncul_di_daftar_absen_kelas_tujuan(): void
    {
        $asal = $this->makeClass('Kelas Asal');
        $tujuan = $this->makeClass('Kelas Tujuan', 2);
        $pindah = $this->makeStudent('Sari', $asal);
        $tanggal = now()->addDays(2)->toDateString();

        $this->replacement([
            'student_id' => $pindah->id,
            'origin_class_id' => $asal->id,
            'missed_date' => now()->addDay()->toDateString(),
            'class_id' => $tujuan->id,
            'replacement_date' => $tanggal,
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $tujuan->id, 'date' => $tanggal]))
            ->assertOk()
            ->assertSee('Sari')
            ->assertSee('Murid pengganti dari');
    }

    /** Di tanggal lain, murid pengganti itu tidak ikut muncul. */
    public function test_murid_pengganti_tidak_muncul_di_tanggal_lain(): void
    {
        $asal = $this->makeClass('Kelas Asal');
        $tujuan = $this->makeClass('Kelas Tujuan', 2);
        $pindah = $this->makeStudent('Sari', $asal);

        $this->replacement([
            'student_id' => $pindah->id,
            'origin_class_id' => $asal->id,
            'missed_date' => now()->addDay()->toDateString(),
            'class_id' => $tujuan->id,
            'replacement_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $tujuan->id, 'date' => now()->addDays(9)->toDateString()]))
            ->assertOk()
            ->assertDontSee('Sari');
    }

    /** Sesi yang ditinggalkan tidak lagi mengabsen muridnya. */
    public function test_murid_yang_memindahkan_sesi_keluar_dari_daftar_absen(): void
    {
        $asal = $this->makeClass('Kelas Asal');
        $tujuan = $this->makeClass('Kelas Tujuan', 2);
        $pindah = $this->makeStudent('Sari', $asal);
        $tetap = $this->makeStudent('Budi', $asal);
        $missed = now()->addDay()->toDateString();

        $this->replacement([
            'student_id' => $pindah->id,
            'origin_class_id' => $asal->id,
            'missed_date' => $missed,
            'class_id' => $tujuan->id,
            'replacement_date' => now()->addDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $asal->id, 'date' => $missed]))
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('memindahkan sesi ini');

        // Namanya tetap disebut di panel penjelas, tapi tak ada baris absen untuknya
        // — dicek lewat hidden input-nya, apa pun urutan barisnya.
        $response->assertDontSee('[student_id]" value="'.$pindah->id.'"', false);
        $response->assertSee('[student_id]" value="'.$tetap->id.'"', false);
    }

    /** Request yang belum disetujui belum memindahkan siapa pun. */
    public function test_request_pending_tidak_mengubah_daftar_absen(): void
    {
        $asal = $this->makeClass('Kelas Asal');
        $tujuan = $this->makeClass('Kelas Tujuan', 2);
        $pindah = $this->makeStudent('Sari', $asal);
        $missed = now()->addDay()->toDateString();
        $tanggal = now()->addDays(2)->toDateString();

        $this->replacement([
            'student_id' => $pindah->id,
            'origin_class_id' => $asal->id,
            'missed_date' => $missed,
            'class_id' => $tujuan->id,
            'replacement_date' => $tanggal,
            'request_status' => 'pending',
        ]);

        $admin = $this->admin();

        // Masih diabsen di kelas asalnya…
        $this->actingAs($admin)
            ->get(route('attendances.create', ['class_id' => $asal->id, 'date' => $missed]))
            ->assertOk()
            ->assertSee('Sari')
            ->assertDontSee('memindahkan sesi ini');

        // …dan belum muncul di kelas tujuan.
        $this->actingAs($admin)
            ->get(route('attendances.create', ['class_id' => $tujuan->id, 'date' => $tanggal]))
            ->assertOk()
            ->assertDontSee('Sari');
    }

    /**
     * Replacement di kelas yang sama: murid tetap terdaftar di kelas itu, jadi ia
     * berpotensi muncul dua kali — sebagai peserta tetap sekaligus pengganti.
     */
    public function test_replacement_di_kelas_yang_sama_tidak_menggandakan_baris(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $murid = $this->makeStudent('Sari', $kelas);
        $tanggal = now()->addDays(8)->toDateString();

        $this->replacement([
            'student_id' => $murid->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => now()->addDay()->toDateString(),
            'class_id' => $kelas->id,
            'replacement_date' => $tanggal,
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $tanggal]))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, '[student_id]" value="'.$murid->id.'"'),
            'Murid seharusnya hanya punya satu baris absen.'
        );
    }

    /**
     * Replacement di kelas yang sama hanya menggeser tanggal sesinya — kelas,
     * tutor, dan jamnya tetap. Maka muridnya harus hilang dari daftar absen
     * tanggal asalnya, dan muncul di tanggal penggantinya.
     */
    public function test_replacement_kelas_sama_hilang_dari_tanggal_asal_muncul_di_tanggal_pengganti(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $pindah = $this->makeStudent('Sari', $kelas);
        $tetap = $this->makeStudent('Budi', $kelas);
        $missed = now()->addDay()->toDateString();
        $pengganti = now()->addDays(8)->toDateString();

        $this->replacement([
            'student_id' => $pindah->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => $missed,
            'class_id' => $kelas->id,
            'replacement_date' => $pengganti,
        ]);

        $admin = $this->admin();

        // Tanggal sesi asal: Sari tidak lagi diabsen, Budi tetap.
        $this->actingAs($admin)
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $missed]))
            ->assertOk()
            ->assertDontSee('[student_id]" value="'.$pindah->id.'"', false)
            ->assertSee('[student_id]" value="'.$tetap->id.'"', false)
            ->assertSee('memindahkan sesi ini');

        // Tanggal pengganti: Sari kembali muncul, ditandai sebagai pengganti,
        // berdampingan dengan Budi yang memang sesi rutinnya.
        $this->actingAs($admin)
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $pengganti]))
            ->assertOk()
            ->assertSee('[student_id]" value="'.$pindah->id.'"', false)
            ->assertSee('[student_id]" value="'.$tetap->id.'"', false)
            ->assertSee('Murid pengganti dari');
    }

    /** Absensi yang sudah tersimpan harus terisi ulang, bukan kembali ke "Hadir". */
    public function test_absensi_tersimpan_terisi_ulang_di_form(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $murid = $this->makeStudent('Sari', $kelas);
        $tanggal = now()->addDay()->toDateString();

        Attendance::create([
            'student_id' => $murid->id,
            'class_id' => $kelas->id,
            'attendance_date' => $tanggal,
            'status' => 'permit',
            'notes' => 'Ada acara keluarga',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $tanggal]))
            ->assertOk()
            ->assertSee('Ada acara keluarga')
            ->assertSee('sudah tercatat');
    }
}
