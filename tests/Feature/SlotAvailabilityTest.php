<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: Aturan ketersediaan slot (F4) — available = tidak ditutup manual, belum penuh,
 * belum lewat, dan tutor aktif. Kecocokan tipe kelas hanya penanda: murid boleh
 * mengambil replacement lintas tipe.
 */
class SlotAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.com', 'username' => 'admin',
            'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Murid siap pakai untuk modul akademik: sudah punya invoice lunas, karena
     * murid yang belum lunas terkunci dari replacement class.
     */
    private function makeStudent(array $overrides = []): Student
    {
        $student = Student::create(array_merge([
            'name' => 'Murid Uji', 'date_of_birth' => '2018-01-01', 'parent_name' => 'Wali',
            'phone_number' => '0812', 'class_type' => 'drawing', 'status' => 'active',
        ], $overrides));

        Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'payment_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        return $student;
    }

    private function makeClass(array $overrides = [], string $tutorStatus = 'full-time'): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => $tutorStatus]);

        return ClassRoom::create(array_merge([
            'class_category' => 'drawing',
            'tutor_id' => $tutor->id,
            'capacity' => 5,
            // Kelas mingguan yang sesi pertamanya besok.
            'schedule_date' => now()->addDay()->toDateString(),
            'schedule_time' => '09:00',
            'class_fee' => 100000,
            'status' => 'open',
        ], $overrides));
    }

    public function test_slot_valid_di_masa_depan_adalah_available(): void
    {
        $this->assertTrue($this->makeClass()->isAvailable());
    }

    public function test_slot_ditutup_manual_tidak_available(): void
    {
        $class = $this->makeClass(['status' => 'closed']);
        $this->assertFalse($class->isAvailable());
        $this->assertSame('secondary', $class->availability()['color']);
    }

    /**
     * Slot mingguan tidak kedaluwarsa: kelas yang dibuat berbulan-bulan lalu tetap
     * available. Ini justru bug yang dulu terjadi saat jadwal masih disimpan
     * sebagai satu tanggal.
     */
    public function test_kelas_lama_tetap_available(): void
    {
        $class = $this->makeClass();
        $class->forceFill(['created_at' => now()->subMonths(6)])->save();

        $class = $class->fresh();
        $this->assertTrue($class->isAvailable());
        $this->assertNotNull($class->nextOccurrence());
        $this->assertTrue($class->nextOccurrence()->isFuture());
    }

    /**
     * Batas bawah sesi diambil dari `created_at`, menggantikan kolom tanggal mulai
     * yang dulu harus diisi admin. Tanpa batas ini kalender akan menampilkan sesi
     * dari sebelum kelasnya ada.
     */
    public function test_sesi_tidak_dihitung_dari_sebelum_kelas_dibuat(): void
    {
        $class = $this->makeClass();

        $sesi = $class->occurrencesBetween(now()->subMonths(2), now()->addWeeks(2));

        $this->assertNotEmpty($sesi);
        foreach ($sesi as $at) {
            $this->assertTrue(
                $at->gte($class->created_at->copy()->startOfDay()),
                'Sesi tidak boleh dihitung dari sebelum kelas dibuat.'
            );
        }
    }

    /**
     * Sesi hari ini yang jamnya sudah lewat bukan lagi "sesi berikutnya" —
     * yang terdekat bergeser sepekan.
     */
    public function test_sesi_hari_ini_yang_jamnya_sudah_lewat_bergeser_sepekan(): void
    {
        $class = $this->makeClass([
            'schedule_date' => now()->subWeek()->toDateString(),
            'schedule_time' => '09:00',
        ]);

        $lewat = $class->occurrenceAt(now()->startOfDay())->addHours(2);

        $this->assertSame(
            now()->addWeek()->toDateString(),
            $class->nextOccurrence($lewat)->toDateString()
        );
        $this->assertTrue($class->isAvailable());
    }

    public function test_occurrences_berulang_mingguan_dalam_rentang(): void
    {
        $class = $this->makeClass();

        $sesi = $class->occurrencesBetween(now(), now()->addWeeks(4));

        // 4 minggu ke depan dari slot yang jatuh besok: 4 atau 5 sesi tergantung
        // posisi hari; yang penting berulang tiap 7 hari dan jamnya konsisten.
        $this->assertGreaterThanOrEqual(4, count($sesi));
        foreach ($sesi as $at) {
            $this->assertSame((int) $class->day_of_week, $at->dayOfWeek);
            $this->assertSame('09:00', $at->format('H:i'));
        }
    }

    public function test_slot_tanpa_tutor_tidak_available(): void
    {
        $class = $this->makeClass();
        // Simulasikan kondisi tanpa tutor (relasi tutor kosong/null).
        $class->setRelation('tutor', null);
        $this->assertFalse($class->isAvailable());
        $this->assertSame('Tutor kosong', $class->availability()['text']);
    }

    public function test_form_kelas_memakai_input_tanggal_dan_dropdown_tipe_kelas(): void
    {
        $this->actingAs($this->makeUser());

        $this->get(route('classes.create'))
            ->assertOk()
            ->assertSee('Tanggal kelas')
            ->assertSee('Tipe kelas')
            ->assertSee('Trial Class')
            ->assertSee('Uang pendaftaran')
            ->assertSee('name="schedule_date"', false)
            ->assertSee('name="class_type"', false)
            ->assertSee('name="registration_fee"', false)
            // Pekan mulai milik murid, bukan kelas. Yang ada di form kelas hanyalah
            // pratinjau harganya — dihitung dari Biaya Kelas, tidak diketik.
            ->assertSee('Harga bulan pertama menurut pekan murid masuk')
            ->assertDontSee('name="start_week"', false)
            ->assertDontSee('name="start_week_fees[1]"', false)
            // Pengulangan tidak lagi diisi admin — diturunkan dari tipe kelas,
            // sama seperti hari yang diturunkan dari tanggal.
            ->assertDontSee('name="is_recurring"', false)
            ->assertDontSee('— Pilih Hari —')
            ->assertDontSee('name="day_of_week"', false);
    }

    public function test_kelas_baru_menurunkan_hari_dari_tanggal(): void
    {
        $this->actingAs($this->makeUser());
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'full-time']);
        $rabu = now()->next(3); // Rabu terdekat

        $this->post(route('classes.store'), [
            'class_category' => 'Drawing Sore',
            'tutor_id' => $tutor->id,
            'capacity' => 8,
            'schedule_date' => $rabu->toDateString(),
            'schedule_time' => '16:00',
            'schedule_end_time' => '17:00',
            'class_type' => 'regular',
            'class_fee' => 300000,
        ])->assertRedirect(route('classes.index'))->assertSessionHasNoErrors();

        $class = ClassRoom::where('class_category', 'Drawing Sore')->firstOrFail();
        // Hari tidak dikirim form & tidak disimpan — diturunkan dari tanggalnya.
        $this->assertSame(3, $class->day_of_week);
        $this->assertTrue($class->is_recurring);
        $this->assertSame('Setiap Rabu, 16:00–17:00', $class->scheduleLabel());
        $this->assertSame(3, $class->nextOccurrence()->dayOfWeek);
    }

    /**
     * Tipe kelas menggantikan saklar pengulangan: trial hanya sekali pertemuan,
     * jadi is_recurring harus ikut turun dari pilihan itu — bukan dikirim form.
     */
    public function test_trial_class_disimpan_sebagai_kelas_sekali_jalan(): void
    {
        $this->actingAs($this->makeUser());
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'full-time']);

        $this->post(route('classes.store'), [
            'class_category' => 'Trial Drawing',
            'tutor_id' => $tutor->id,
            'capacity' => 4,
            'schedule_date' => now()->addDay()->toDateString(),
            'schedule_time' => '10:00',
            'schedule_end_time' => '11:00',
            'class_type' => 'trial',
            'class_fee' => 75000,
            'registration_fee' => 50000,
        ])->assertRedirect(route('classes.index'))->assertSessionHasNoErrors();

        $class = ClassRoom::where('class_category', 'Trial Drawing')->firstOrFail();
        $this->assertTrue($class->isTrial());
        $this->assertFalse($class->is_recurring);
        $this->assertEqualsWithDelta(125000.0, $class->initialFee(), 0.01);
    }

    // ─── JAM MULAI & SELESAI ───────────────────────────────────────

    /** Jam selesai wajib diisi, dan tidak boleh mendahului jam mulai. */
    public function test_jam_selesai_harus_setelah_jam_mulai(): void
    {
        $this->actingAs($this->makeUser());
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'full-time']);

        $payload = [
            'class_category' => 'Drawing Terbalik',
            'tutor_id' => $tutor->id,
            'capacity' => 8,
            'schedule_date' => now()->addDay()->toDateString(),
            'schedule_time' => '16:00',
            'class_type' => 'regular',
            'class_fee' => 300000,
        ];

        $this->from(route('classes.create'))
            ->post(route('classes.store'), $payload + ['schedule_end_time' => '15:00'])
            ->assertSessionHasErrors('schedule_end_time');

        $this->from(route('classes.create'))
            ->post(route('classes.store'), $payload)
            ->assertSessionHasErrors(['schedule_end_time' => 'Jam selesai belum diisi.']);

        $this->assertDatabaseMissing('classes', ['class_category' => 'Drawing Terbalik']);
    }

    /** Jam kelas disebut sebagai rentang, dan lamanya bisa dihitung. */
    public function test_label_jadwal_menyebut_rentang_jam(): void
    {
        $class = $this->makeClass([
            'schedule_time' => '09:00',
            'schedule_end_time' => '10:30',
        ]);

        $this->assertSame('09:00–10:30', $class->timeRangeLabel());
        $this->assertSame(90, $class->durationMinutes());
        $this->assertStringContainsString('09:00–10:30', $class->scheduleLabel());

        $sesi = $class->nextOccurrence();
        $this->assertSame('10:30', $class->occurrenceEndAt($sesi)->format('H:i'));
    }

    /**
     * Slot lama belum punya jam selesai. Ia harus tetap tampil apa adanya —
     * bukan dilengkapi tebakan yang lalu terbaca sebagai jadwal resmi.
     */
    public function test_slot_tanpa_jam_selesai_tampil_seperti_sedia_kala(): void
    {
        $class = $this->makeClass(['schedule_time' => '09:00']);

        $this->assertNull($class->endTimeLabel());
        $this->assertNull($class->durationMinutes());
        $this->assertNull($class->occurrenceEndAt($class->nextOccurrence()));
        $this->assertSame('09:00', $class->timeRangeLabel());
    }

    // ─── PANEL KALENDER DI MANAJEMEN KELAS ─────────────────────────

    /** Kalender jadwal kini juga bisa dibuka dari Manajemen Kelas. */
    public function test_panel_kalender_tampil_di_manajemen_kelas(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass();

        $this->get(route('classes.index', ['tab' => 'kalender']))
            ->assertOk()
            ->assertSee('Kalender jadwal')
            ->assertSee('id="calendar"', false)
            ->assertSee($class->class_category);
    }

    /**
     * Tab lain tidak ikut menyusun eventnya: satu slot mingguan merentang jadi
     * ratusan kejadian, dan itu pekerjaan yang tak ada gunanya di halaman daftar.
     */
    public function test_tab_kelas_tidak_memuat_kalender(): void
    {
        $this->actingAs($this->makeUser());
        $this->makeClass();

        $this->get(route('classes.index'))
            ->assertOk()
            ->assertDontSee('id="calendar"', false);
    }

    /**
     * Uang pendaftaran bersifat add-on: dikosongkan berarti nol, bukan gagal validasi.
     */
    public function test_uang_pendaftaran_boleh_dikosongkan(): void
    {
        $this->actingAs($this->makeUser());
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'full-time']);

        $this->post(route('classes.store'), [
            'class_category' => 'Coloring Pagi',
            'tutor_id' => $tutor->id,
            'capacity' => 6,
            'schedule_date' => now()->addDay()->toDateString(),
            'schedule_time' => '08:00',
            'schedule_end_time' => '09:00',
            'class_type' => 'regular',
            'class_fee' => 200000,
            'registration_fee' => '',
        ])->assertRedirect(route('classes.index'))->assertSessionHasNoErrors();

        $class = ClassRoom::where('class_category', 'Coloring Pagi')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $class->registration_fee, 0.01);
        $this->assertEqualsWithDelta(200000.0, $class->initialFee(), 0.01);
    }

    public function test_kelas_sekali_jalan_hanya_punya_satu_sesi(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass(['is_recurring' => false]);

        $sesi = $class->occurrencesBetween(now(), now()->addWeeks(6));

        $this->assertCount(1, $sesi);
        $this->assertSame($class->schedule_date->toDateString(), $sesi[0]->toDateString());
        $this->assertStringContainsString($class->schedule_date->format('d M Y'), $class->scheduleLabel());
    }

    /**
     * Perbedaan pokok antara kedua mode: kelas sekali jalan memang kedaluwarsa
     * setelah tanggalnya lewat, kelas mingguan tidak pernah.
     */
    public function test_kelas_sekali_jalan_kedaluwarsa_setelah_tanggalnya_lewat(): void
    {
        $lewat = ['schedule_date' => now()->subWeek()->toDateString()];

        $sekaliJalan = $this->makeClass($lewat + ['is_recurring' => false]);
        $this->assertNull($sekaliJalan->nextOccurrence());
        $this->assertFalse($sekaliJalan->isAvailable());
        $this->assertSame('Sudah lewat', $sekaliJalan->availability()['text']);

        $mingguan = $this->makeClass($lewat);
        $this->assertNotNull($mingguan->nextOccurrence());
        $this->assertTrue($mingguan->isAvailable());
    }

    /**
     * Filter "Hari" tidak lagi memakai WHERE — hari diturunkan dari schedule_date,
     * jadi penyaringan & paginasinya dilakukan di PHP.
     */
    public function test_filter_hari_menyaring_daftar_kelas(): void
    {
        $this->actingAs($this->makeUser());
        $this->makeClass(['class_category' => 'Kelas Senin', 'schedule_date' => now()->next(1)->toDateString()]);
        $this->makeClass(['class_category' => 'Kelas Kamis', 'schedule_date' => now()->next(4)->toDateString()]);
        $this->makeClass(['class_category' => 'Kelas Minggu', 'schedule_date' => now()->next(0)->toDateString()]);

        // Kelas Senin kedua sengaja dibuat belakangan tapi berjam lebih pagi:
        // saat satu hari dipilih, urutannya harus jadwal, bukan kelas terbaru.
        $this->makeClass([
            'class_category' => 'Kelas Senin Pagi',
            'schedule_date' => now()->next(1)->toDateString(),
            'schedule_time' => '07:00',
        ]);

        // Kelas sekali jalan pada Senin yang sudah lewat: masih ada di inventaris,
        // tapi tidak lagi "jalan hari Senin".
        $this->makeClass([
            'class_category' => 'Kelas Senin Lampau',
            'schedule_date' => now()->subWeek()->next(1)->toDateString(),
            'is_recurring' => false,
        ]);

        $senin = $this->get(route('classes.index', ['day' => 1]))->assertOk();
        $this->assertSame(
            ['Kelas Senin Pagi', 'Kelas Senin'],
            $senin->viewData('classes')->pluck('class_category')->all()
        );

        // Minggu = 0. Nilai ini mudah terjatuh sebagai "kosong" di pengecekan
        // filter, jadi sengaja ikut diuji.
        $minggu = $this->get(route('classes.index', ['day' => 0]))->assertOk();
        $this->assertSame(['Kelas Minggu'], $minggu->viewData('classes')->pluck('class_category')->all());

        // Tanpa filter, semuanya tampil — termasuk yang sekali jalan & sudah lewat.
        $semua = $this->get(route('classes.index'))->assertOk();
        $this->assertContains('Kelas Senin Lampau', $semua->viewData('classes')->pluck('class_category')->all());
        $this->assertCount(5, $semua->viewData('classes'));
    }

    public function test_daftar_kelas_menampilkan_jadwal_mingguan(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass();

        $this->get(route('classes.index'))
            ->assertOk()
            ->assertSee($class->scheduleLabel())
            ->assertSee('Sesi berikutnya')
            // Kelas mingguan tak pernah kedaluwarsa.
            ->assertDontSee('Sudah lewat');
    }

    public function test_kecocokan_level_hanya_penanda_bukan_syarat(): void
    {
        $class = $this->makeClass(['class_category' => 'drawing']);
        $coloring = Student::create([
            'name' => 'A', 'date_of_birth' => '2018-01-01', 'parent_name' => 'B',
            'phone_number' => '0812', 'class_type' => 'coloring', 'status' => 'active',
        ]);
        $drawing = Student::create([
            'name' => 'C', 'date_of_birth' => '2018-01-01', 'parent_name' => 'D',
            'phone_number' => '0813', 'class_type' => 'drawing', 'status' => 'active',
        ]);

        // Penanda "pas untuk murid" tetap membedakan tipe...
        $this->assertFalse($class->isAvailableFor($coloring));
        $this->assertTrue($class->isAvailableFor($drawing));
        // ...tapi slotnya sendiri tetap available untuk siapa pun.
        $this->assertTrue($class->isAvailable());
    }

    public function test_replacement_lintas_tipe_kelas_diterima(): void
    {
        $this->actingAs($this->makeUser());
        $origin = $this->makeClass(['class_category' => 'coloring']);
        $target = $this->makeClass(['class_category' => 'drawing']);
        $student = $this->makeStudent(['name' => 'Dina', 'parent_name' => 'Eka', 'class_type' => 'coloring']);

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $origin->id,
            'class_id' => $target->id, // beda tipe dari murid — harus tetap diterima
            'replacement_date' => now()->addDays(3)->toDateString(),
            'replacement_time' => '09:00',
            'reason' => 'Sakit',
        ])->assertRedirect(route('schedules.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('replacement_requests', [
            'student_id' => $student->id,
            'origin_class_id' => $origin->id,
            'class_id' => $target->id,
            'request_status' => 'pending',
        ]);
    }

    /**
     * Kelas adalah slot mingguan, jadi "pindah ke kelas yang sama" wajar — asalkan
     * sesinya benar-benar berpindah. Di sini harinya berbeda dari jadwal kelas asal.
     */
    public function test_kelas_asal_boleh_sama_bila_harinya_berbeda(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass(); // slot jatuh pada hari besok, 09:00
        $student = $this->makeStudent(['name' => 'Eko', 'parent_name' => 'Fani']);

        $hariLain = now()->addDays(3); // dua hari setelah hari slot — pasti beda hari

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $class->id,
            'class_id' => $class->id,
            'replacement_date' => $hariLain->toDateString(),
            'replacement_time' => '09:00',
        ])->assertRedirect(route('schedules.index'))->assertSessionHasNoErrors();

        $saved = ReplacementRequest::firstOrFail();
        $this->assertSame($class->id, $saved->origin_class_id);
        $this->assertSame($class->id, $saved->class_id);
        $this->assertSame($hariLain->toDateString(), $saved->replacement_date->toDateString());
    }

    public function test_kelas_asal_boleh_sama_bila_jamnya_berbeda(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass();
        $student = $this->makeStudent(['name' => 'Putri', 'parent_name' => 'Rian']);

        // Hari yang sama dengan slot (sepekan setelahnya), tapi jamnya digeser.
        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $class->id,
            'class_id' => $class->id,
            'replacement_date' => now()->addDays(8)->toDateString(),
            'replacement_time' => '14:00',
        ])->assertRedirect(route('schedules.index'))->assertSessionHasNoErrors();

        $this->assertSame('14:00', substr(ReplacementRequest::firstOrFail()->replacement_time, 0, 5));
    }

    /**
     * Kelas, hari, dan jam yang sama tapi tanggal berbeda: murid menyusul di sesi
     * pekan berikutnya. Sesinya benar-benar berpindah, jadi sah — yang dibandingkan
     * tanggal persis, bukan hari mingguannya.
     */
    public function test_kelas_asal_sama_di_sesi_pekan_berikutnya_diizinkan(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass(); // sesi terdekat: besok, 09:00
        $student = $this->makeStudent(['name' => 'Sari', 'parent_name' => 'Tono']);

        $pekanDepan = now()->addDays(8); // hari yang sama dengan sesi terdekat, sepekan setelahnya

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $class->id,
            'class_id' => $class->id,
            'replacement_date' => $pekanDepan->toDateString(),
            'replacement_time' => '09:00',
        ])->assertRedirect(route('schedules.index'))->assertSessionHasNoErrors();

        $this->assertSame($pekanDepan->toDateString(), ReplacementRequest::firstOrFail()->replacement_date->toDateString());
    }

    /**
     * "Tanggal sesi yang dilewatkan" harus benar-benar sesi kelas asal. Dropdown di
     * form sudah membatasinya, tapi itu hanya penjaga sisi browser — tanggal lain
     * tak pernah cocok dengan absensi, jadi muridnya tidak akan pernah dikeluarkan
     * dari sesi mana pun.
     */
    public function test_missed_date_di_luar_sesi_kelas_asal_ditolak(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass(); // sesi mingguan: besok, 09:00
        $student = $this->makeStudent(['name' => 'Wira', 'parent_name' => 'Yuli']);

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $class->id,
            'missed_date' => now()->addDays(3)->toDateString(), // dua hari dari hari kelas
            'class_id' => $class->id,
            'replacement_date' => now()->addDays(8)->toDateString(),
            'replacement_time' => '09:00',
        ])->assertSessionHasErrors('missed_date');

        $this->assertDatabaseCount('replacement_requests', 0);
    }

    /** Sesi kelas asal yang sudah lewat tetap boleh — kelas pengganti sering diminta belakangan. */
    public function test_missed_date_sesi_lampau_kelas_asal_diterima(): void
    {
        $this->actingAs($this->makeUser());
        // Kelas sudah berjalan sebulan, jadi punya sesi lampau yang nyata —
        // sesi sebelum kelasnya dimulai memang tidak pernah ada.
        $class = $this->makeClass(['schedule_date' => now()->addDay()->subWeeks(4)->toDateString()]);
        $student = $this->makeStudent(['name' => 'Xena', 'parent_name' => 'Zaki']);

        // Hari kelas yang sama, sepekan sebelum sesi terdekat.
        $lampau = now()->addDay()->subWeek();

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $class->id,
            'missed_date' => $lampau->toDateString(),
            'class_id' => $class->id,
            'replacement_date' => now()->addDays(8)->toDateString(),
            'replacement_time' => '09:00',
        ])->assertRedirect(route('schedules.index'))->assertSessionHasNoErrors();

        $this->assertSame($lampau->toDateString(), ReplacementRequest::firstOrFail()->missed_date->toDateString());
    }

    /**
     * Yang tetap ditolak: sesi pengganti jatuh tepat pada sesi yang ditinggalkan —
     * kelas, tanggal, dan jam sama persis. Tidak ada yang berpindah.
     */
    public function test_kelas_asal_sama_pada_sesi_yang_ditinggalkan_ditolak(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass();
        $student = $this->makeStudent(['name' => 'Sari', 'parent_name' => 'Tono']);

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $class->id,
            'class_id' => $class->id,
            'replacement_date' => now()->addDay()->toDateString(), // sesi terdekat kelas asal
            'replacement_time' => '09:00',
        ])->assertSessionHasErrors('replacement_date');

        $this->assertDatabaseCount('replacement_requests', 0);
    }

    /**
     * Tanggal & jam yang dikosongkan diisi dari jadwal kelas tujuan. Untuk kelas
     * yang sama, sesi terdekatnya justru sesi yang sedang ditinggalkan — jadi
     * isian otomatisnya digeser ke sesi sesudahnya, bukan ditolak.
     */
    public function test_kelas_asal_sama_tanpa_isian_tanggal_digeser_ke_sesi_berikutnya(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass();
        $student = $this->makeStudent(['name' => 'Umar', 'parent_name' => 'Vina']);

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $class->id,
            'class_id' => $class->id,
            // tanggal & jam sengaja tidak dikirim
        ])->assertRedirect(route('schedules.index'))->assertSessionHasNoErrors();

        $saved = ReplacementRequest::firstOrFail();
        $this->assertSame(now()->addDays(8)->toDateString(), $saved->replacement_date->toDateString());
        $this->assertSame('09:00', substr($saved->replacement_time, 0, 5));
    }

    public function test_tanggal_pengganti_yang_sudah_lewat_ditolak(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass();
        $student = $this->makeStudent(['name' => 'Lina', 'parent_name' => 'Mira']);

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'replacement_date' => now()->subDay()->toDateString(),
            'replacement_time' => '09:00',
        ])->assertSessionHasErrors('replacement_date');
    }

    public function test_tanggal_jam_pengganti_kosong_ikut_jadwal_kelas_tujuan(): void
    {
        $this->actingAs($this->makeUser());
        $target = $this->makeClass([
            'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:30',
        ]);
        $student = $this->makeStudent(['name' => 'Gani', 'parent_name' => 'Hesti']);

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'class_id' => $target->id,
            // replacement_date & replacement_time sengaja tidak dikirim.
        ])->assertRedirect(route('schedules.index'))->assertSessionHasNoErrors();

        $saved = ReplacementRequest::firstOrFail();
        $this->assertSame($target->id, $saved->class_id);
        $this->assertSame(now()->addDays(5)->toDateString(), $saved->replacement_date->toDateString());
        $this->assertSame('14:30', substr($saved->replacement_time, 0, 5));
    }

    public function test_tanggal_jam_pengganti_manual_tidak_ditimpa_jadwal_kelas(): void
    {
        $this->actingAs($this->makeUser());
        $target = $this->makeClass([
            'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:30',
        ]);
        $student = $this->makeStudent(['name' => 'Ika', 'parent_name' => 'Joko']);

        // Sesi digeser dari jadwal aslinya — isian admin harus dipakai apa adanya.
        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'class_id' => $target->id,
            'replacement_date' => now()->addDays(6)->toDateString(),
            'replacement_time' => '16:00',
        ])->assertRedirect(route('schedules.index'))->assertSessionHasNoErrors();

        $saved = ReplacementRequest::firstOrFail();
        $this->assertSame(now()->addDays(6)->toDateString(), $saved->replacement_date->toDateString());
        $this->assertSame('16:00', substr($saved->replacement_time, 0, 5));
    }

    public function test_halaman_jadwal_menampilkan_ringkasan_dan_legenda(): void
    {
        $this->actingAs($this->makeUser());
        $this->makeClass();

        $this->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Slot tersedia')
            ->assertSee('Replacement Pending')
            ->assertSee('Ketersediaan slot kelas');
    }

    public function test_panel_slot_mengelompokkan_kelas_per_kategori(): void
    {
        $this->actingAs($this->makeUser());

        // Dua jadwal Coloring & satu Drawing: kategori harus jadi satu kepala
        // kelompok, bukan tiga baris sejajar.
        $this->makeClass(['class_category' => 'coloring', 'capacity' => 10]);
        $this->makeClass(['class_category' => 'Coloring', 'schedule_time' => '13:00', 'capacity' => 4]);
        $this->makeClass(['class_category' => 'drawing', 'capacity' => 6]);

        $halaman = $this->get(route('schedules.index', ['tab' => 'slots']))->assertOk();

        $halaman->assertSee('slot-group-head', false)
            // 10 + 4 kursi Coloring dijumlahkan di kepala kelompoknya.
            ->assertSee('14 kursi tersisa')
            ->assertSee('6 kursi tersisa');
    }

    /**
     * Kategori diketik bebas admin, jadi "Coloring" dan "coloring" harus jatuh di
     * satu kelompok — sama seperti pencocokan tipe kelas di tempat lain.
     */
    public function test_kategori_beda_besar_kecil_huruf_jadi_satu_kelompok(): void
    {
        $this->actingAs($this->makeUser());

        $this->makeClass(['class_category' => 'coloring']);
        $this->makeClass(['class_category' => 'COLORING', 'schedule_time' => '13:00']);

        $groups = $this->get(route('schedules.index', ['tab' => 'slots']))
            ->assertOk()
            ->viewData('slotGroups');

        $this->assertCount(1, $groups);
        $this->assertSame(2, $groups->first()['total']);
    }

    /**
     * Pop-up detail slot dan kalender dijalankan roster yang sama, jadi keduanya
     * tak bisa menyebut tutor atau jumlah murid yang berbeda untuk kelas yang sama.
     */
    public function test_panel_slot_mengirim_roster_berisi_tutor_dan_murid(): void
    {
        $this->actingAs($this->makeUser());

        $class = $this->makeClass();
        $student = $this->makeStudent();
        $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);

        $rosters = $this->get(route('schedules.index', ['tab' => 'slots']))
            ->assertOk()
            ->viewData('rosters');

        $roster = $rosters[$class->id];

        $this->assertSame('Kak Tutor', $roster['tutor']);
        $this->assertSame(1, $roster['enrolled']);
        $this->assertTrue($roster['available']);
        $this->assertSame([$student->name], array_column($roster['students'], 'name'));
        // Pintu ke pendaftaran anak membawa slot ini, bukan sekadar kategorinya.
        $this->assertStringContainsString('class_id='.$class->id, $roster['enrollUrl']);
    }

    public function test_kalender_menampilkan_pemilih_murid_untuk_cari_pengganti(): void
    {
        $this->actingAs($this->makeUser());
        $this->makeClass();

        $this->get(route('schedules.calendar'))
            ->assertOk()
            ->assertSee('Cari kelas pengganti')
            ->assertSee('replacementStudent', false);
    }

    /**
     * Toggle "Hanya slot available" menyaring di sisi klien memakai penanda
     * `past` dari server, jadi yang diuji di sini adalah penandanya: replacement
     * yang jadwalnya lewat ditandai true apa pun statusnya — pending yang
     * terlewat pun tidak bisa dipakai lagi.
     */
    public function test_replacement_yang_sudah_lewat_ditandai_past_di_kalender(): void
    {
        $this->actingAs($this->makeUser());

        $student = $this->makeStudent();
        $origin = $this->makeClass();
        $target = $this->makeClass(['class_category' => 'Kelas Tujuan']);

        // Satu per status, semuanya di masa lalu.
        foreach (['pending', 'approved', 'rejected'] as $status) {
            ReplacementRequest::create([
                'student_id' => $student->id,
                'origin_class_id' => $origin->id,
                'class_id' => $target->id,
                'replacement_date' => now()->subWeek()->toDateString(),
                'replacement_time' => '09:00',
                'request_status' => $status,
            ]);
        }

        $events = $this->calendarEvents('Replacement Class');

        $this->assertCount(3, $events);
        foreach ($events as $event) {
            $this->assertTrue($event['extendedProps']['past'], 'Replacement lewat harus ditandai past.');
        }
    }

    /**
     * Event kalender bertipe tertentu, di-decode dari payload halaman.
     *
     * Sejak kelas jadi slot mingguan, halaman kalender memuat banyak event kelas
     * reguler yang juga membawa penanda `past`. Jadi penanda replacement harus
     * diperiksa per event, bukan dengan mencocokkan string di seluruh halaman.
     *
     * @return list<array<string, mixed>>
     */
    private function calendarEvents(string $type): array
    {
        $content = $this->get(route('schedules.calendar'))->assertOk()->getContent();

        // `const events = [...];` dirender dalam satu baris oleh @json.
        preg_match('/const events = (\[.*\]);/', $content, $matches);
        $events = json_decode($matches[1] ?? '[]', true) ?: [];

        return array_values(array_filter(
            $events,
            fn (array $event) => ($event['extendedProps']['type'] ?? null) === $type
        ));
    }

    public function test_replacement_mendatang_tidak_ditandai_past(): void
    {
        $this->actingAs($this->makeUser());

        $student = $this->makeStudent();
        $origin = $this->makeClass();
        $target = $this->makeClass(['class_category' => 'Kelas Tujuan']);

        ReplacementRequest::create([
            'student_id' => $student->id,
            'origin_class_id' => $origin->id,
            'class_id' => $target->id,
            'replacement_date' => now()->addWeek()->toDateString(),
            'replacement_time' => '09:00',
            'request_status' => 'pending',
        ]);

        $events = $this->calendarEvents('Replacement Class');

        $this->assertCount(1, $events);
        $this->assertFalse($events[0]['extendedProps']['past']);
    }

    public function test_kelas_mingguan_direntangkan_jadi_banyak_event_kalender(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass();

        $events = $this->calendarEvents('Kelas Reguler');

        // Satu slot mingguan menghasilkan banyak sesi dalam rentang tampilan —
        // dulu hanya muncul sekali seumur hidup kelas.
        $this->assertGreaterThan(10, count($events));
        $this->assertSame($class->scheduleLabel(), $events[0]['extendedProps']['schedule']);
    }

    public function test_pesan_validasi_kelas_kosong_berbahasa_indonesia(): void
    {
        $this->actingAs($this->makeUser());
        $student = $this->makeStudent(['name' => 'Citra', 'parent_name' => 'Ani']);

        // Kirim tanpa class_id — meniru kondisi dropdown kosong karena tak ada slot cocok.
        $response = $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'replacement_date' => now()->addDay()->toDateString(),
            'replacement_time' => '09:00',
            'reason' => 'd',
        ]);

        $response->assertSessionHasErrors('class_id');
        $this->assertStringContainsString(
            'Kelas tujuan belum dipilih',
            session('errors')->first('class_id')
        );
    }

    public function test_form_replacement_terisi_dari_query_prefill(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass(['class_category' => 'drawing']);
        $student = $this->makeStudent(['name' => 'Budi', 'parent_name' => 'Ani']);

        $this->get(route('schedules.create', ['student_id' => $student->id, 'class_id' => $class->id]))
            ->assertOk()
            ->assertSee($student->name);
    }
}
