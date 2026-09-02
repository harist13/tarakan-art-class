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
 * QA: dua kolom baru di daftar absen.
 *
 *   "Sesi Bulan Ini" — berapa sesi yang sudah dihadiri murid dari jatah bulanan,
 *   dihitung lintas kelas karena sesi pengganti pun memakai jatah yang sama.
 *
 *   "Replacement?"  — pintasan ke Manajemen Jadwal. Pilihannya tidak disimpan
 *   sebagai data absensi; yang menentukan "Ya" adalah request pengganti yang
 *   memang sudah ada untuk sesi tersebut.
 */
class AttendanceSessionQuotaTest extends TestCase
{
    use RefreshDatabase;

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

    /** Tanggal ke-n bulan berjalan — supaya hitungan "bulan ini" tidak terpengaruh
     *  hari saat tes dijalankan. */
    private function tanggalBulanIni(int $day): string
    {
        return now()->startOfMonth()->addDays($day - 1)->toDateString();
    }

    /**
     * Yang dihitung hanya kehadiran nyata: izin & absen tidak memakai jatah, dan
     * bulan lain tidak ikut terbawa.
     */
    public function test_kolom_sesi_menghitung_kehadiran_bulan_ini_saja(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $murid = $this->makeStudent('Sari', $kelas);

        $this->hadir($murid, $kelas, $this->tanggalBulanIni(3));
        $this->hadir($murid, $kelas, $this->tanggalBulanIni(10));
        $this->hadir($murid, $kelas, $this->tanggalBulanIni(17), 'permit');
        $this->hadir($murid, $kelas, now()->startOfMonth()->subMonth()->addDays(2)->toDateString());

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $this->tanggalBulanIni(24)]))
            ->assertOk()
            ->assertSee('Sesi Bulan Ini')
            ->assertSee('Sari sudah hadir 2 dari 4 sesi');
    }

    /** Sesi pengganti dijalani di kelas lain, tapi tetap memakai jatah yang sama. */
    public function test_kehadiran_di_kelas_pengganti_ikut_dihitung(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $lain = $this->makeClass('Kelas Coloring', 2);
        $murid = $this->makeStudent('Sari', $kelas);

        $this->hadir($murid, $kelas, $this->tanggalBulanIni(3));
        $this->hadir($murid, $lain, $this->tanggalBulanIni(6));

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $this->tanggalBulanIni(10)]))
            ->assertOk()
            ->assertSee('Sari sudah hadir 2 dari 4 sesi');
    }

    /**
     * Murid tanpa request pengganti: dropdown terbuka di "Tidak", dan pensilnya
     * mengarah ke form pengajuan baru yang sudah terisi murid + sesinya.
     */
    public function test_murid_tanpa_replacement_mengarah_ke_form_pengajuan_baru(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $murid = $this->makeStudent('Sari', $kelas);
        $tanggal = $this->tanggalBulanIni(10);

        $html = $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $tanggal]))
            ->assertOk()
            ->assertSee('Replacement?')
            ->getContent();

        $this->assertStringContainsString('schedules/create?student_id='.$murid->id, $html);
        $this->assertStringContainsString('origin_class_id='.$kelas->id, $html);
        $this->assertStringContainsString('missed_date='.$tanggal, $html);
        // Pensilnya baru muncul setelah dropdown-nya dipilih "Ya".
        $this->assertStringContainsString('flex-shrink-0 d-none', $html);
    }

    /**
     * Pengajuan yang masih pending belum memindahkan sesi, jadi muridnya tetap
     * diabsen di sini — tapi kolomnya sudah menunjuk ke request yang ada supaya
     * admin tidak mengajukan permintaan kedua untuk sesi yang sama.
     */
    public function test_request_pending_menandai_replacement_dan_mengarah_ke_request_itu(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $tujuan = $this->makeClass('Kelas Coloring', 2);
        $murid = $this->makeStudent('Sari', $kelas);
        $tanggal = $this->tanggalBulanIni(10);

        $req = ReplacementRequest::create([
            'student_id' => $murid->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => $tanggal,
            'class_id' => $tujuan->id,
            'replacement_date' => $this->tanggalBulanIni(17),
            'replacement_time' => '09:00',
            'request_status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $tanggal]))
            ->assertOk()
            // Muridnya belum berpindah — masih ada barisnya.
            ->assertSee('[student_id]" value="'.$murid->id.'"', false)
            ->assertSee('schedules/'.$req->id.'/edit', false)
            ->assertSee('Menunggu persetujuan');
    }

    /**
     * "Ya" tanpa jadwal pengganti menahan penyimpanan: tandanya tidak ikut tersimpan
     * di absensi, jadi kalau dibiarkan lewat, ia hilang tanpa jejak dan sesi
     * penggantinya tidak pernah benar-benar diatur.
     */
    public function test_absensi_ditolak_bila_replacement_ya_belum_dijadwalkan(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $murid = $this->makeStudent('Sari', $kelas);
        $tanggal = $this->tanggalBulanIni(10);

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $kelas->id,
                'attendance_date' => $tanggal,
                'records' => [
                    ['student_id' => $murid->id, 'status' => 'present', 'replacement' => 'ya'],
                ],
            ])
            ->assertSessionHasErrors('replacement');

        $this->assertDatabaseCount('attendances', 0);
    }

    /** Begitu jadwal penggantinya ada, absensinya tersimpan seperti biasa. */
    public function test_absensi_tersimpan_bila_replacement_sudah_dijadwalkan(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $tujuan = $this->makeClass('Kelas Coloring', 2);
        $murid = $this->makeStudent('Sari', $kelas);
        $lain = $this->makeStudent('Budi', $kelas);
        $tanggal = $this->tanggalBulanIni(10);

        ReplacementRequest::create([
            'student_id' => $murid->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => $tanggal,
            'class_id' => $tujuan->id,
            'replacement_date' => $this->tanggalBulanIni(17),
            'replacement_time' => '09:00',
            'request_status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $kelas->id,
                'attendance_date' => $tanggal,
                'records' => [
                    ['student_id' => $murid->id, 'status' => 'present', 'replacement' => 'ya'],
                    ['student_id' => $lain->id, 'status' => 'present', 'replacement' => 'tidak'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendances', 2);
    }

    /**
     * Mengabsen sesi yang sama dua kali memperbarui catatannya, bukan menumpuk
     * baris baru — kalau tidak, satu sesi bisa punya dua kehadiran untuk satu
     * murid dan rekapnya ikut terhitung dobel.
     */
    public function test_menyimpan_ulang_sesi_yang_sama_memperbarui_catatannya(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $murid = $this->makeStudent('Sari', $kelas);
        $tanggal = $this->tanggalBulanIni(10);

        $admin = $this->admin();

        $simpan = fn (string $status) => $this->actingAs($admin)
            ->post(route('attendances.store'), [
                'class_id' => $kelas->id,
                'attendance_date' => $tanggal,
                'records' => [['student_id' => $murid->id, 'status' => $status]],
            ])->assertSessionHasNoErrors();

        $simpan('present');
        $simpan('absent');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame('absent', Attendance::first()->status);
    }

    /**
     * Murid yang masih punya sesi pengganti belum terlampaui tidak ikut diabsen
     * di sesi reguler — kehadirannya dicatat di sesi penggantinya. Berlaku walau
     * missed_date-nya menunjuk tanggal lain: kolom itu sering terisi otomatis.
     */
    public function test_murid_dengan_sesi_pengganti_mendatang_keluar_dari_daftar_absen(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $pindah = $this->makeStudent('Bella', $kelas);
        $tetap = $this->makeStudent('Elang', $kelas);

        ReplacementRequest::create([
            'student_id' => $pindah->id,
            'origin_class_id' => $kelas->id,
            // Sesi yang ditinggalkan menunjuk tanggal lain, bukan sesi di bawah.
            'missed_date' => now()->addDays(20)->toDateString(),
            'class_id' => $kelas->id,
            'replacement_date' => now()->addDays(13)->toDateString(),
            'replacement_time' => '10:00',
            'request_status' => 'approved',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => now()->addDays(6)->toDateString()]))
            ->assertOk()
            ->assertDontSee('[student_id]" value="'.$pindah->id.'"', false)
            ->assertSee('[student_id]" value="'.$tetap->id.'"', false)
            ->assertSee('memindahkan sesi ini');
    }

    /** Pada tanggal sesi penggantinya, muridnya kembali masuk daftar absen. */
    public function test_murid_kembali_diabsen_pada_tanggal_sesi_penggantinya(): void
    {
        $kelas = $this->makeClass('Kelas Coloring');
        $pindah = $this->makeStudent('Bella', $kelas);
        $pengganti = now()->addDays(13)->toDateString();

        ReplacementRequest::create([
            'student_id' => $pindah->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => now()->addDays(20)->toDateString(),
            'class_id' => $kelas->id,
            'replacement_date' => $pengganti,
            'replacement_time' => '10:00',
            'request_status' => 'approved',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $pengganti]))
            ->assertOk()
            ->assertSee('[student_id]" value="'.$pindah->id.'"', false)
            ->assertSee('Sesi pengganti')
            // Tidak boleh sekaligus dilaporkan meninggalkan sesi ini.
            ->assertDontSee('memindahkan sesi ini');
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
            'missed_date' => now()->subDays(20)->toDateString(),
            'class_id' => $kelas->id,
            'replacement_date' => now()->subDays(14)->toDateString(),
            'replacement_time' => '10:00',
            'request_status' => 'approved',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee('[student_id]" value="'.$murid->id.'"', false)
            ->assertDontSee('memindahkan sesi ini');
    }

    /** Pengajuan yang masih pending menyebut sesi pengganti yang dimintanya. */
    public function test_pengajuan_pending_menyebut_tanggal_dan_kelas_penggantinya(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $tujuan = $this->makeClass('Kelas Coloring', 2);
        $murid = $this->makeStudent('Sari', $kelas);
        $tanggal = now()->addDay()->toDateString();

        ReplacementRequest::create([
            'student_id' => $murid->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => $tanggal,
            'class_id' => $tujuan->id,
            'replacement_date' => now()->addDays(15)->toDateString(),
            'replacement_time' => '10:00',
            'request_status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $tanggal]))
            ->assertOk()
            ->assertSee('Menunggu persetujuan')
            ->assertSee(now()->addDays(15)->locale('id')->translatedFormat('j M Y'));
    }

    /** Murid pengganti menyebut sesi mana yang sedang disusulnya. */
    public function test_murid_pengganti_menyebut_sesi_yang_digantikan(): void
    {
        $asal = $this->makeClass('Kelas Drawing');
        $tujuan = $this->makeClass('Kelas Coloring', 2);
        $murid = $this->makeStudent('Sari', $asal);
        $missed = now()->addDay()->toDateString();
        $pengganti = now()->addDays(9)->toDateString();

        ReplacementRequest::create([
            'student_id' => $murid->id,
            'origin_class_id' => $asal->id,
            'missed_date' => $missed,
            'class_id' => $tujuan->id,
            'replacement_date' => $pengganti,
            'replacement_time' => '10:00',
            'request_status' => 'approved',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $tujuan->id, 'date' => $pengganti]))
            ->assertOk()
            ->assertSee('menggantikan sesi '.now()->addDay()->locale('id')->translatedFormat('j M Y'));
    }

    /** Murid yang sesinya sudah berpindah tetap bisa diubah jadwalnya dari sini. */
    public function test_murid_yang_pindah_punya_tautan_ubah_jadwal(): void
    {
        $kelas = $this->makeClass('Kelas Drawing');
        $tujuan = $this->makeClass('Kelas Coloring', 2);
        $murid = $this->makeStudent('Sari', $kelas);
        $tanggal = $this->tanggalBulanIni(10);

        $req = ReplacementRequest::create([
            'student_id' => $murid->id,
            'origin_class_id' => $kelas->id,
            'missed_date' => $tanggal,
            'class_id' => $tujuan->id,
            'replacement_date' => $this->tanggalBulanIni(17),
            'replacement_time' => '09:00',
            'request_status' => 'approved',
        ]);

        $admin = $this->admin();

        // Di sesi asal: muridnya keluar dari daftar, tautan ubahnya ada di catatan.
        $this->actingAs($admin)
            ->get(route('attendances.create', ['class_id' => $kelas->id, 'date' => $tanggal]))
            ->assertOk()
            ->assertSee('memindahkan sesi ini')
            ->assertSee('schedules/'.$req->id.'/edit', false);

        // Di sesi pengganti: muridnya diabsen sebagai pengganti, kolom Replacement
        // sudah "Ya" dan pensilnya menunjuk request yang sama.
        $this->actingAs($admin)
            ->get(route('attendances.create', ['class_id' => $tujuan->id, 'date' => $this->tanggalBulanIni(17)]))
            ->assertOk()
            ->assertSee('Murid pengganti dari')
            ->assertSee('schedules/'.$req->id.'/edit', false);
    }
}
