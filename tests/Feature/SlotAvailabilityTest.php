<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\ClassRoom;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: Aturan ketersediaan slot (F4) — available = tidak ditutup manual, belum penuh,
 * belum lewat, bukan hari libur, tutor aktif, dan (per murid) level cocok.
 */
class SlotAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ClassRoom::flushHolidayCache();
    }

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

    private function makeClass(array $overrides = [], string $tutorStatus = 'active'): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => $tutorStatus]);

        return ClassRoom::create(array_merge([
            'class_name' => 'Kelas Drawing',
            'class_category' => 'drawing',
            'tutor_id' => $tutor->id,
            'capacity' => 5,
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

    public function test_slot_sudah_lewat_tidak_available(): void
    {
        $class = $this->makeClass(['schedule_date' => now()->subDay()->toDateString()]);
        $this->assertFalse($class->isAvailable());
        $this->assertSame('Sudah lewat', $class->availability()['text']);
    }

    public function test_slot_tanpa_tutor_aktif_tidak_available(): void
    {
        $class = $this->makeClass(tutorStatus: 'inactive');
        $this->assertFalse($class->isAvailable());
        $this->assertSame('Tutor kosong', $class->availability()['text']);
    }

    public function test_slot_pada_hari_libur_tidak_available(): void
    {
        $class = $this->makeClass();
        Holiday::create(['date' => $class->schedule_date->toDateString(), 'name' => 'Libur']);
        ClassRoom::flushHolidayCache();

        $this->assertTrue($class->isHoliday());
        $this->assertFalse($class->isAvailable());
        $this->assertSame('Hari libur', $class->availability()['text']);
    }

    public function test_level_harus_cocok_dengan_tipe_kelas_murid(): void
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

        $this->assertFalse($class->isAvailableFor($coloring));
        $this->assertTrue($class->isAvailableFor($drawing));
    }

    public function test_halaman_jadwal_menampilkan_ringkasan_dan_legenda(): void
    {
        $this->actingAs($this->makeUser());
        $this->makeClass();

        $this->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Slot Tersedia')
            ->assertSee('Replacement Pending')
            ->assertSee('Ketersediaan Slot Kelas');
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

    public function test_hari_libur_muncul_di_kalender_walau_tanpa_jadwal(): void
    {
        $this->actingAs($this->makeUser());
        Holiday::create(['date' => now()->addWeek()->toDateString(), 'name' => 'Libur Nasional']);

        $this->get(route('schedules.calendar'))
            ->assertOk()
            ->assertSee('Libur Nasional')
            ->assertSee('Hari Libur'); // legenda + extendedProps
    }

    public function test_admin_dapat_menambah_acara_dan_muncul_di_kalender(): void
    {
        $this->actingAs($this->makeUser());
        $date = now()->addDays(3)->toDateString();

        $this->post(route('calendar-events.store'), [
            'title' => 'Rapat Guru',
            'date' => $date,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'description' => 'Evaluasi bulanan',
            'color' => '#6366F1',
        ])->assertRedirect();

        $this->assertDatabaseHas('calendar_events', ['title' => 'Rapat Guru']);

        $this->get(route('schedules.calendar'))->assertOk()->assertSee('Rapat Guru');

        $event = CalendarEvent::first();
        $this->delete(route('calendar-events.destroy', $event))->assertRedirect();
        $this->assertDatabaseMissing('calendar_events', ['id' => $event->id]);
    }

    public function test_acara_jam_selesai_harus_setelah_jam_mulai(): void
    {
        $this->actingAs($this->makeUser());

        $this->post(route('calendar-events.store'), [
            'title' => 'Salah Jam',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '14:00',
        ])->assertSessionHasErrors('end_time', null, 'event');
    }

    public function test_pesan_validasi_kelas_kosong_berbahasa_indonesia(): void
    {
        $this->actingAs($this->makeUser());
        $student = Student::create([
            'name' => 'Citra', 'date_of_birth' => '2018-01-01', 'parent_name' => 'Ani',
            'phone_number' => '0812', 'class_type' => 'drawing', 'status' => 'active',
        ]);

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
        $student = Student::create([
            'name' => 'Budi', 'date_of_birth' => '2018-01-01', 'parent_name' => 'Ani',
            'phone_number' => '0812', 'class_type' => 'drawing', 'status' => 'active',
        ]);

        $this->get(route('schedules.create', ['student_id' => $student->id, 'class_id' => $class->id]))
            ->assertOk()
            ->assertSee($student->name);
    }

    public function test_admin_dapat_menambah_dan_menghapus_hari_libur(): void
    {
        $this->actingAs($this->makeUser());
        $date = now()->addWeek()->toDateString();

        $this->post(route('holidays.store'), ['date' => $date, 'name' => 'Libur Nasional'])
            ->assertRedirect();
        $this->assertDatabaseHas('holidays', ['name' => 'Libur Nasional']);

        $holiday = Holiday::first();
        $this->delete(route('holidays.destroy', $holiday))->assertRedirect();
        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }
}
