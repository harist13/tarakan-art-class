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

    /**
     * Kalender digambar sebagai petak hari x jam mengikuti jam buka sanggar,
     * bukan tampilan bulan. Angkanya harus datang dari ClassRoom — grid yang
     * pelan-pelan menyimpang dari slot yang bisa dipilih di form kelas akan
     * menggambar kelas di luar petaknya.
     */
    public function test_kalender_digambar_sebagai_petak_hari_kali_jam(): void
    {
        $this->actingAs($this->admin());
        $this->makeClass();

        $content = $this->get(route('classes.index', ['tab' => 'kalender']))->assertOk()->getContent();

        $this->assertStringContainsString("initialView: 'timeGridWeek'", $content);
        $this->assertStringContainsString('"'.ClassRoom::SLOT_START.':00"', $content);
        $this->assertStringContainsString('"'.ClassRoom::SLOT_END.':00"', $content);
        // Digambar tiap 30 menit supaya kelas di luar irama 1,5 jam (Preschool
        // 16:00-17:00) tergambar di posisi sebenarnya; labelnya tetap 1,5 jam.
        $this->assertStringContainsString("slotDuration: '00:30:00'", $content);
        $this->assertStringContainsString('slotLabelInterval: slotDurasi', $content);
        $this->assertStringContainsString('"01:30:00"', $content);
        $this->assertStringContainsString('function bukaSlot(', $content);
        // Kalender berbahasa Indonesia: bundel locale-nya ikut dimuat, dan kepala
        // kolom hari tidak menunggu bundel itu untuk jadi "Senin".
        $this->assertStringContainsString("locale: 'id'", $content);
        $this->assertStringContainsString('locales/id.global.min.js', $content);
        $this->assertStringContainsString('dayHeaderContent:', $content);
        // Tampilan pekan memakai kadar warnanya sendiri — seulas tipis dengan
        // bilah di tepi — sementara warna dasarnya tetap satu sumber.
        $this->assertStringContainsString("setProperty('--ev'", $content);
        $this->assertStringContainsString('border-left: 4px solid var(--ev', $content);
        // Paling banyak dua jadwal berdampingan dalam satu kolom hari; lebih
        // dari itu tak ada kata yang muat. Kelas didahulukan atas replacement.
        $this->assertStringContainsString('eventMaxStack: 2', $content);
        $this->assertStringContainsString('eventOrder: function', $content);
    }

    /**
     * Badge di tampilan pekan bisa dijangkau papan ketik.
     *
     * FullCalendar menggambarnya sebagai <a> tanpa href, jadi tanpa perlakuan
     * khusus ia dilewati Tab begitu saja — jadwal yang hanya bisa dibuka dengan
     * tetikus menutup pintu bagi yang tidak memakainya.
     */
    public function test_badge_dan_tautan_lain_bisa_dijangkau_papan_ketik(): void
    {
        $this->actingAs($this->admin());
        $this->makeClass();

        $content = $this->get(route('classes.index', ['tab' => 'kalender']))->assertOk()->getContent();

        $this->assertStringContainsString("setAttribute('tabindex', '0')", $content);
        $this->assertStringContainsString("setAttribute('role', 'button')", $content);
        $this->assertStringContainsString("setAttribute('aria-label'", $content);
        $this->assertStringContainsString('function aksesTautanLain(', $content);
        // Cincin fokus harus ikut ada; tabindex tanpa jejak di layar sama saja
        // dengan navigasi yang tak bisa diikuti mata.
        $this->assertStringContainsString(':focus-visible', $content);
    }

    /**
     * Warna badge punya arti (biru kelas, hijau replacement disetujui), jadi
     * hover tidak boleh menggesernya — yang berubah hanya kedalamannya.
     */
    public function test_hover_badge_tidak_menggeser_warnanya(): void
    {
        $this->actingAs($this->admin());
        $this->makeClass();

        $content = $this->get(route('classes.index', ['tab' => 'kalender']))->assertOk()->getContent();

        $blokHover = strstr($content, '#calendar .fc-timegrid-event:hover {');
        $blokHover = substr($blokHover, 0, (int) strpos($blokHover, '}'));

        $this->assertStringContainsString('box-shadow', $blokHover);
        $this->assertStringContainsString('border-left-color', $blokHover);
        $this->assertStringNotContainsString('background-color', $blokHover);

        // Badge yang sedang dibuka tetap ditandai setelah modalnya ditutup.
        $this->assertStringContainsString('.fc-timegrid-event.is-selected', $content);
        $this->assertStringContainsString('function tandaiTerpilih(', $content);
    }

    /**
     * Di sesi pendek nama tutor mengalah, bukan judulnya.
     *
     * Blok sesi 60 menit (Preschool 16:00-17:00) hanya setinggi judul dua baris.
     * Yang dikorbankan tutornya — ia masih terbaca di tooltip & di daftar petak,
     * sedangkan nama kelas tidak punya cadangan semacam itu.
     */
    public function test_nama_tutor_hanya_di_sesi_yang_cukup_panjang(): void
    {
        $this->actingAs($this->admin());
        $this->makeClass();

        $content = $this->get(route('classes.index', ['tab' => 'kalender']))->assertOk()->getContent();

        $this->assertStringContainsString('if (menit < slotMenit) return;', $content);
        // Grid tetap pendek: 1,6rem per setengah jam.
        $this->assertStringContainsString('#calendar .fc-timegrid-slot { height: 1.6rem; }', $content);
    }

    /** Di layar sempit nama tutor turun ke tooltip, bukan dipaksa muat. */
    public function test_nama_tutor_disembunyikan_di_layar_sempit(): void
    {
        $this->actingAs($this->admin());
        $this->makeClass();

        $content = $this->get(route('classes.index', ['tab' => 'kalender']))->assertOk()->getContent();

        $this->assertStringContainsString('@media (max-width: 767.98px)', $content);
        $this->assertStringContainsString('#calendar .fc-event-tutor { display: none; }', $content);
        // Tooltipnya memang memuat tutor, jadi keterangan itu tidak hilang.
        $this->assertStringContainsString("'Tutor: ' + p.tutor", $content);
    }
}
