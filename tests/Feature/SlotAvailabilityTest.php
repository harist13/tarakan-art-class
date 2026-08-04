<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\ClassRoom;
use App\Models\Holiday;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: Aturan ketersediaan slot (F4) — available = tidak ditutup manual, belum penuh,
 * belum lewat, bukan hari libur, dan tutor aktif. Kecocokan tipe kelas hanya penanda:
 * murid boleh mengambil replacement lintas tipe.
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
        $origin = $this->makeClass(['class_name' => 'Kelas Coloring', 'class_category' => 'coloring']);
        $target = $this->makeClass(['class_name' => 'Kelas Drawing', 'class_category' => 'drawing']);
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

    public function test_kelas_asal_tidak_boleh_sama_dengan_kelas_baru(): void
    {
        $this->actingAs($this->makeUser());
        $class = $this->makeClass();
        $student = $this->makeStudent(['name' => 'Eko', 'parent_name' => 'Fani']);

        $this->post(route('schedules.store'), [
            'student_id' => $student->id,
            'origin_class_id' => $class->id,
            'class_id' => $class->id,
            'replacement_date' => now()->addDays(3)->toDateString(),
            'replacement_time' => '09:00',
        ])->assertSessionHasErrors('origin_class_id');
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
