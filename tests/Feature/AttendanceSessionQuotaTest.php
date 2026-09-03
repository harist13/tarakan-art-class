<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: absensi sebagai daftar centang yang ditarik dari jadwal.
 *
 * Halaman ini hanya menanyakan tanggal; sesi yang berjalan hari itu dan isinya
 * diturunkan dari jadwal. Yang diuji di sini:
 *   - sesi hari itu terbuka sendiri, tanpa memilih kelas,
 *   - centang tersimpan hadir, izin menang atas centang, sisanya absen,
 *   - jatah "sesi bulan ini" per murid,
 *   - kolom Replacement: penandanya, dan penjaga "harus dijadwalkan dulu".
 *
 * Waktunya dibekukan pada awal September 2026 supaya tanggal sesi mingguan dan
 * batas bulan tidak berpindah mengikuti hari tes dijalankan.
 */
class AttendanceSessionQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 1 September 2026 — sesi kelas di bawah jatuh tiap Rabu.
        $this->travelTo(Carbon::create(2026, 9, 1, 8));
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
        $tutor = Tutor::create(['name' => 'Kak '.$name, 'status' => 'full-time']);

        return ClassRoom::create([
            'class_category' => $name,
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

    private function hadir(Student $student, ClassRoom $class, string $date, string $status = 'present'): void
    {
        Attendance::create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'attendance_date' => $date,
            'status' => $status,
        ]);
    }

    // ─── BENTUK HALAMAN ────────────────────────────────────────────

    /** Semua sesi pada satu hari terbuka sekaligus — kelas tidak dipilih manual. */
    public function test_semua_sesi_pada_tanggal_itu_langsung_tampil(): void
    {
        $pagi = $this->makeClass('Kelas Coloring');
        $siang = $this->makeClass('Kelas Preschool');
        $lainHari = $this->makeClass('Kelas Drawing', 2);

        $this->makeStudent('Bella', $pagi);
        $this->makeStudent('Doni', $siang);
        $this->makeStudent('Sari', $lainHari);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-02']))
            ->assertOk()
            ->assertSee('Kelas Coloring')
            ->assertSee('Kelas Preschool')
            ->assertSee('Bella')
            ->assertSee('Doni')
            // Kelas yang jadwalnya di hari lain tidak ikut terbuka.
            ->assertDontSee('Kelas Drawing');
    }

    /** Hari tanpa sesi tidak menampilkan form apa pun. */
    public function test_hari_tanpa_sesi_kosong(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $this->makeStudent('Bella', $kelas);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-03']))
            ->assertOk()
            ->assertSee('Tidak ada sesi kelas')
            ->assertDontSee('Bella');
    }

    // ─── MENYIMPAN ─────────────────────────────────────────────────

    /** Dicentang = hadir, ditandai izin = izin, sisanya tidak hadir. */
    public function test_centang_izin_dan_sisanya_tersimpan_dengan_benar(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $datang = $this->makeStudent('Bella', $kelas);
        $izin = $this->makeStudent('Elang', $kelas);
        $bolos = $this->makeStudent('Rian', $kelas);

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $kelas->id,
                'attendance_date' => '2026-09-02',
                'students' => [$datang->id, $izin->id, $bolos->id],
                'present' => [$datang->id],
                'permit' => [$izin->id],
                'notes' => [$izin->id => 'Ada acara keluarga'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('attendances.create', ['date' => '2026-09-02']));

        $this->assertSame('present', Attendance::where('student_id', $datang->id)->value('status'));
        $this->assertSame('permit', Attendance::where('student_id', $izin->id)->value('status'));
        $this->assertSame('absent', Attendance::where('student_id', $bolos->id)->value('status'));
        $this->assertSame('Ada acara keluarga', Attendance::where('student_id', $izin->id)->value('notes'));
    }

    /** Izin menang atas centang — keduanya tidak mungkin benar bersamaan. */
    public function test_izin_mengalahkan_centang_hadir(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $murid = $this->makeStudent('Bella', $kelas);

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $kelas->id,
                'attendance_date' => '2026-09-02',
                'students' => [$murid->id],
                'present' => [$murid->id],
                'permit' => [$murid->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('permit', Attendance::where('student_id', $murid->id)->value('status'));
    }

    /** Menyimpan ulang sesi yang sama memperbarui catatannya, bukan menggandakan. */
    public function test_menyimpan_ulang_memperbarui_catatan_yang_ada(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $murid = $this->makeStudent('Bella', $kelas);

        $this->hadir($murid, $kelas, '2026-09-02');

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $kelas->id,
                'attendance_date' => '2026-09-02',
                'students' => [$murid->id],
                // Centangnya dilepas — kehadirannya dibatalkan.
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame('absent', Attendance::first()->status);
    }

    // ─── JATAH SESI BULANAN ────────────────────────────────────────

    /** Hanya kehadiran nyata yang dihitung, dan bulan lain tidak ikut terbawa. */
    public function test_jatah_sesi_menghitung_kehadiran_bulan_ini_saja(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $murid = $this->makeStudent('Bella', $kelas);

        $this->hadir($murid, $kelas, '2026-09-02');
        $this->hadir($murid, $kelas, '2026-09-09');
        $this->hadir($murid, $kelas, '2026-09-16', 'permit');
        $this->hadir($murid, $kelas, '2026-08-26');

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-23']))
            ->assertOk()
            ->assertSee('Bella sudah hadir 2 dari 4 sesi');
    }

    /** Sesi pengganti dijalani di kelas lain, tapi tetap memakai jatah yang sama. */
    public function test_kehadiran_di_kelas_pengganti_ikut_dihitung(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $lain = $this->makeClass('Kelas Drawing', 2);
        $murid = $this->makeStudent('Bella', $kelas);

        $this->hadir($murid, $kelas, '2026-09-02');
        $this->hadir($murid, $lain, '2026-09-03');

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-09']))
            ->assertOk()
            ->assertSee('Bella sudah hadir 2 dari 4 sesi');
    }

    // ─── KOLOM REPLACEMENT ─────────────────────────────────────────

    /**
     * Murid tanpa request pengganti: dropdown terbuka di "Tidak", dan pensilnya
     * mengarah ke form pengajuan baru yang sudah terisi murid + sesinya.
     */
    public function test_murid_tanpa_replacement_mengarah_ke_form_pengajuan_baru(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $murid = $this->makeStudent('Bella', $kelas);

        $html = $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-02']))
            ->assertOk()
            ->assertSee('Replacement?')
            ->getContent();

        $this->assertStringContainsString('schedules/create?student_id='.$murid->id, $html);
        $this->assertStringContainsString('origin_class_id='.$kelas->id, $html);
        $this->assertStringContainsString('missed_date=2026-09-02', $html);
    }

    /**
     * Pengajuan yang masih pending belum memindahkan sesi, jadi muridnya tetap
     * diabsen di sini — tapi kolomnya sudah menunjuk request yang ada supaya admin
     * tidak mengajukan permintaan kedua untuk sesi yang sama.
     */
    public function test_request_pending_menandai_replacement_dan_mengarah_ke_request_itu(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $tujuan = $this->makeClass('Kelas Drawing', 2);
        $murid = $this->makeStudent('Bella', $kelas);

        $req = ReplacementRequest::create([
            'student_id' => $murid->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => '2026-09-02',
            'class_id' => $tujuan->id,
            'replacement_date' => '2026-09-17',
            'replacement_time' => '09:00',
            'request_status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-02']))
            ->assertOk()
            ->assertSee('students[]" value="'.$murid->id.'"', false)
            ->assertSee('schedules/'.$req->id.'/edit', false)
            ->assertSee('Menunggu persetujuan');
    }

    /** "Replacement: Ya" tanpa jadwal pengganti menahan penyimpanan. */
    public function test_absensi_ditolak_bila_replacement_ya_belum_dijadwalkan(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $murid = $this->makeStudent('Bella', $kelas);

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $kelas->id,
                'attendance_date' => '2026-09-02',
                'students' => [$murid->id],
                'present' => [$murid->id],
                'replacement' => [$murid->id => 'ya'],
            ])
            ->assertSessionHasErrors('replacement');

        $this->assertDatabaseCount('attendances', 0);
    }

    /** Begitu jadwal penggantinya ada, absensinya tersimpan seperti biasa. */
    public function test_absensi_tersimpan_bila_replacement_sudah_dijadwalkan(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $tujuan = $this->makeClass('Kelas Drawing', 2);
        $murid = $this->makeStudent('Bella', $kelas);
        $lain = $this->makeStudent('Elang', $kelas);

        ReplacementRequest::create([
            'student_id' => $murid->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => '2026-09-02',
            'class_id' => $tujuan->id,
            'replacement_date' => '2026-09-17',
            'replacement_time' => '09:00',
            'request_status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $kelas->id,
                'attendance_date' => '2026-09-02',
                'students' => [$murid->id, $lain->id],
                'present' => [$murid->id, $lain->id],
                'replacement' => [$murid->id => 'ya', $lain->id => 'tidak'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendances', 2);
    }

    // ─── MURID YANG SESINYA PINDAH ─────────────────────────────────

    /**
     * Murid yang masih punya sesi pengganti belum terlampaui tidak ikut diabsen
     * di sesi reguler — kehadirannya dicatat di sesi penggantinya. Berlaku walau
     * missed_date-nya menunjuk tanggal lain: kolom itu sering terisi otomatis.
     */
    public function test_murid_dengan_sesi_pengganti_mendatang_keluar_dari_daftar(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $pindah = $this->makeStudent('Bella', $kelas);
        $tetap = $this->makeStudent('Elang', $kelas);

        ReplacementRequest::create([
            'student_id' => $pindah->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => '2026-09-23',
            'class_id' => $kelas->id,
            'replacement_date' => '2026-09-16',
            'replacement_time' => '09:00',
            'request_status' => 'approved',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-09']))
            ->assertOk()
            ->assertDontSee('students[]" value="'.$pindah->id.'"', false)
            ->assertSee('students[]" value="'.$tetap->id.'"', false)
            ->assertSee('Jadwalnya pindah');
    }

    /** Pada tanggal sesi penggantinya, muridnya kembali masuk daftar centang. */
    public function test_murid_kembali_diabsen_pada_tanggal_sesi_penggantinya(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $pindah = $this->makeStudent('Bella', $kelas);

        ReplacementRequest::create([
            'student_id' => $pindah->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => '2026-09-23',
            'class_id' => $kelas->id,
            'replacement_date' => '2026-09-16',
            'replacement_time' => '09:00',
            'request_status' => 'approved',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-16']))
            ->assertOk()
            ->assertSee('students[]" value="'.$pindah->id.'"', false)
            ->assertSee('pengganti dari')
            // Tidak boleh sekaligus dilaporkan meninggalkan sesi ini.
            ->assertDontSee('Jadwalnya pindah');
    }

    /**
     * Sesi pengganti yang sudah terlampaui bukan lagi agenda: muridnya kembali
     * diabsen seperti biasa di sesi reguler sesudahnya.
     */
    public function test_murid_kembali_diabsen_setelah_sesi_penggantinya_lewat(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $murid = $this->makeStudent('Bella', $kelas);

        ReplacementRequest::create([
            'student_id' => $murid->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => '2026-09-02',
            'class_id' => $kelas->id,
            'replacement_date' => '2026-09-09',
            'replacement_time' => '09:00',
            'request_status' => 'approved',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['date' => '2026-09-16']))
            ->assertOk()
            ->assertSee('students[]" value="'.$murid->id.'"', false)
            ->assertDontSee('Jadwalnya pindah');
    }
}
