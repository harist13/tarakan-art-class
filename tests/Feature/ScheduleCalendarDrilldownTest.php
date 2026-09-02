<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use App\Support\ScheduleCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: penelusuran bertingkat di kalender jadwal — tanggal → jam → tutor & murid
 * → data murid.
 *
 * Yang dijaga di sini adalah datanya, bukan kliknya: roster tiap kelas harus
 * membawa tutor, jam, dan murid aktifnya; murid titipan (replacement) hanya
 * menempel pada tanggal sesinya sendiri dan hanya bila sudah disetujui.
 */
class ScheduleCalendarDrilldownTest extends TestCase
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

    private function makeClass(string $category = 'drawing', string $tutorName = 'Kak Sari'): ClassRoom
    {
        $tutor = Tutor::create(['name' => $tutorName, 'phone_number' => '081200000001', 'status' => 'full-time']);

        return ClassRoom::create([
            'class_category' => $category,
            'tutor_id' => $tutor->id,
            'capacity' => 8,
            'schedule_date' => now()->addDay()->toDateString(),
            'schedule_time' => '09:00',
            'schedule_end_time' => '10:30',
            'class_fee' => 300000,
            'status' => 'open',
        ]);
    }

    private function makeStudent(string $name, ?ClassRoom $class = null): Student
    {
        $student = Student::create([
            'name' => $name, 'date_of_birth' => '2018-01-01', 'parent_name' => 'Ibu '.$name,
            'phone_number' => '0812', 'class_type' => 'drawing', 'status' => 'active',
        ]);

        Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'payment_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        if ($class) {
            $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);
        }

        return $student;
    }

    // ─── ROSTER: TUTOR & MURID PER KELAS ───────────────────────────

    public function test_roster_membawa_tutor_jam_dan_murid_aktif(): void
    {
        $class = $this->makeClass();
        $this->makeStudent('Budi', $class);
        $this->makeStudent('Sari', $class);

        $roster = app(ScheduleCalendar::class)->rosters()[$class->id];

        $this->assertSame('Kak Sari', $roster['tutor']);
        $this->assertSame('09:00–10:30', $roster['time']);
        $this->assertSame(2, $roster['enrolled']);
        $this->assertSame(8, $roster['capacity']);
        $this->assertSame(['Budi', 'Sari'], array_column($roster['students'], 'name'));
        // Tiap murid membawa tautan ke form datanya — tingkat terakhir penelusuran.
        $this->assertStringContainsString('/students/', $roster['students'][0]['url']);
    }

    /**
     * Murid yang sudah keluar dari kelas tidak boleh ikut terdaftar: tutor akan
     * menyiapkan kursi untuk anak yang tidak lagi datang.
     */
    public function test_roster_mengabaikan_murid_yang_tidak_aktif(): void
    {
        $class = $this->makeClass();
        $keluar = $this->makeStudent('Mantan', $class);
        $class->students()->updateExistingPivot($keluar->id, ['status' => 'inactive']);
        $this->makeStudent('Aktif', $class);

        $roster = app(ScheduleCalendar::class)->rosters()[$class->id];

        $this->assertSame(['Aktif'], array_column($roster['students'], 'name'));
    }

    // ─── MURID TITIPAN (REPLACEMENT) ───────────────────────────────

    private function ajukan(Student $student, ClassRoom $origin, ClassRoom $target, string $status, string $tanggal): void
    {
        ReplacementRequest::create([
            'student_id' => $student->id,
            'origin_class_id' => $origin->id,
            'class_id' => $target->id,
            'replacement_date' => $tanggal,
            'replacement_time' => '09:00',
            'request_status' => $status,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sesiKelas(int $classId): array
    {
        return array_values(array_filter(
            app(ScheduleCalendar::class)->events(),
            fn (array $ev) => ($ev['extendedProps']['classId'] ?? null) === $classId
        ));
    }

    public function test_murid_titipan_hanya_menempel_pada_tanggal_sesinya(): void
    {
        $target = $this->makeClass('drawing');
        $origin = $this->makeClass('coloring', 'Kak Rina');
        $titipan = $this->makeStudent('Anak Titipan', $origin);

        // Sesi kelas tujuan yang paling dekat — itu tanggal yang dititipi.
        $tanggal = $target->nextOccurrence()->toDateString();
        $this->ajukan($titipan, $origin, $target, 'approved', $tanggal);

        $sesi = collect($this->sesiKelas($target->id));
        $dititipi = $sesi->firstWhere('start', $tanggal.'T09:00:00');

        $this->assertNotNull($dititipi, 'Sesi pada tanggal itu harus ada di kalender.');
        $this->assertSame(['Anak Titipan'], array_column($dititipi['extendedProps']['guests'], 'name'));

        // Sesi lain kelas yang sama tidak ikut kebagian.
        $lain = $sesi->reject(fn ($ev) => $ev['start'] === $tanggal.'T09:00:00');
        $this->assertTrue($lain->every(fn ($ev) => $ev['extendedProps']['guests'] === []));
    }

    /**
     * Pengajuan yang belum disetujui belum tentu jadi. Menampilkannya sebagai
     * peserta membuat tutor menyiapkan kursi untuk anak yang mungkin tak datang.
     */
    public function test_replacement_pending_belum_terhitung_sebagai_murid_titipan(): void
    {
        $target = $this->makeClass('drawing');
        $origin = $this->makeClass('coloring', 'Kak Rina');
        $murid = $this->makeStudent('Anak Pending', $origin);

        $tanggal = $target->nextOccurrence()->toDateString();
        $this->ajukan($murid, $origin, $target, 'pending', $tanggal);

        $dititipi = collect($this->sesiKelas($target->id))->firstWhere('start', $tanggal.'T09:00:00');

        $this->assertSame([], $dititipi['extendedProps']['guests']);
    }

    // ─── HALAMAN ───────────────────────────────────────────────────

    /** Tombol pengajuan replacement di kepala halaman kini bernama "Ubah". */
    public function test_tombol_ubah_menggantikan_ajukan_replacement(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('schedules.calendar'))
            ->assertOk()
            ->assertSee('id="btnUbahJadwal"', false)
            ->assertDontSee('Ajukan Replacement</a>', false);
    }

    /** Panel kalender membawa roster-nya, jadi penelusuran tidak perlu memuat ulang. */
    public function test_panel_kalender_membawa_roster_kelas(): void
    {
        $this->actingAs($this->admin());
        $class = $this->makeClass();
        $this->makeStudent('Budi', $class);

        $content = $this->get(route('classes.index', ['tab' => 'kalender']))->assertOk()->getContent();

        $this->assertStringContainsString('levelJam', $content);
        $this->assertStringContainsString('levelKelas', $content);
        $this->assertStringContainsString('Budi', $content);
        $this->assertStringContainsString('Kak Sari', $content);
    }
}
