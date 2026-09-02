<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: Menu "Murid & Wali" (Student Management / F2).
 * Menguji index+filter, create, store+validasi, edit, update, dan destroy (otorisasi).
 */
class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        Role::firstOrCreate(['name' => $role]);
        $user = User::create([
            'full_name' => ucfirst($role).' User',
            'email' => $role.'@example.com',
            'username' => $role,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeClass(string $category = 'drawing', int $capacity = 5, string $status = 'open'): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'full-time']);

        return ClassRoom::create([
            'class_category' => $category,
            'tutor_id' => $tutor->id,
            'capacity' => $capacity,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'class_fee' => 100000,
            'status' => $status,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => '081234567890',
            'instagram_username' => null,
            'address' => 'Jl. Mawar 1',
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => '2026-01-15',
        ], $overrides);
    }

    // ─── INDEX + FILTER ────────────────────────────────────────────

    public function test_index_page_loads(): void
    {
        $this->makeClass();
        Student::create($this->validPayload(['name' => 'Ani Listing']));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Data Murid & Wali', false)
            ->assertSee('Ani Listing');
    }

    public function test_index_search_filters_by_name(): void
    {
        Student::create($this->validPayload(['name' => 'Zaki Unik']));
        Student::create($this->validPayload(['name' => 'Lain Orang']));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.index', ['search' => 'Zaki']))
            ->assertOk()
            ->assertSee('Zaki Unik')
            ->assertDontSee('Lain Orang');
    }

    public function test_index_filters_by_status(): void
    {
        Student::create($this->validPayload(['name' => 'Si Aktif', 'status' => 'active']));
        Student::create($this->validPayload(['name' => 'Si Nonaktif', 'status' => 'inactive']));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Si Nonaktif')
            ->assertDontSee('Si Aktif');
    }

    public function test_index_filters_by_class(): void
    {
        $classA = $this->makeClass('drawing');
        $classB = $this->makeClass('coloring');

        $inA = Student::create($this->validPayload(['name' => 'Murid Kelas A']));
        $inA->classes()->attach($classA->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);
        $inB = Student::create($this->validPayload(['name' => 'Murid Kelas B']));
        $inB->classes()->attach($classB->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.index', ['class_id' => $classA->id]))
            ->assertOk()
            ->assertSee('Murid Kelas A')
            ->assertDontSee('Murid Kelas B');
    }

    // ─── CREATE + STORE ────────────────────────────────────────────

    public function test_create_page_loads(): void
    {
        $this->makeClass();

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('Tambah Murid Baru');
    }

    public function test_store_creates_student_and_enrolls_class(): void
    {
        $class = $this->makeClass('drawing');
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)->post(route('students.store'), $this->validPayload([
            'class_type' => 'drawing',
        ]));

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        $student = Student::first();
        $this->assertNotNull($student);
        $this->assertSame('STD001', $student->student_id, 'ID murid harus auto-generate STD001');
        $this->assertTrue($student->classes->contains($class->id), 'Murid harus terdaftar ke kelas');
        $this->assertSame('active', $student->classes->first()->pivot->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'created']);
    }

    /**
     * Pekan mulai dicatat pada pendaftaran murid ke kelas, bukan pada muridnya:
     * satu kelas dimasuki anak-anak yang datang di pekan berbeda-beda.
     */
    public function test_store_menyimpan_pekan_mulai_di_pendaftaran_kelas(): void
    {
        $class = $this->makeClass('drawing');
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('students.store'), $this->validPayload([
            'start_week' => 3,
        ]))->assertRedirect(route('students.index'))->assertSessionHasNoErrors();

        $student = Student::with('classes')->first();

        $this->assertSame(3, (int) $student->classes->first()->pivot->start_week);
        $this->assertSame(3, $student->startWeek());
    }

    /**
     * Tanpa pilihan admin, pekan diturunkan dari tanggal murid terdaftar ke
     * kelas — bukan dibiarkan kosong lalu tertagih harga termahal.
     */
    public function test_pekan_mulai_terisi_sendiri_dari_tanggal_pendaftaran(): void
    {
        $this->makeClass('drawing');
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('students.store'), $this->validPayload())
            ->assertRedirect(route('students.index'))->assertSessionHasNoErrors();

        $student = Student::with('classes')->first();
        $harapan = min(\App\Models\ClassRoom::WEEKS_PER_MONTH, intdiv(now()->day - 1, 7) + 1);

        $this->assertSame($harapan, (int) $student->classes->first()->pivot->start_week);
    }

    /**
     * Pekan mulai tampil sebagai keterangan yang terisi sendiri, bukan kotak isian
     * tersendiri — tapi pilihannya tetap ada di balik "Ubah" dan tetap terkirim.
     */
    public function test_form_murid_menampilkan_pekan_mulai_yang_bisa_diubah(): void
    {
        $this->makeClass('drawing');

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('Minggu ke-1')
            ->assertSee('dari 4 pekan di bulan pertama')
            ->assertSee('Ubah')
            ->assertSee('name="start_week"', false);
    }

    public function test_student_id_auto_increments(): void
    {
        $this->makeClass('drawing');
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('students.store'), $this->validPayload(['name' => 'Pertama']));
        $this->actingAs($admin)->post(route('students.store'), $this->validPayload(['name' => 'Kedua']));

        $this->assertSame(['STD001', 'STD002'], Student::orderBy('id')->pluck('student_id')->all());
    }

    public function test_store_requires_mandatory_fields(): void
    {
        $this->makeClass();

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload(['name' => '', 'class_type' => '']))
            ->assertSessionHasErrors(['name', 'class_type']);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_store_rejects_non_numeric_phone(): void
    {
        $this->makeClass();

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload(['phone_number' => '0812-abc']))
            ->assertSessionHasErrors('phone_number');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_store_rejects_full_or_closed_class_type(): void
    {
        $this->makeClass('drawing', capacity: 0, status: 'closed');

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload(['class_type' => 'drawing']))
            ->assertSessionHasErrors('class_type');
    }

    // ─── EDIT + UPDATE ─────────────────────────────────────────────

    public function test_edit_page_loads(): void
    {
        $this->makeClass();
        $student = Student::create($this->validPayload(['name' => 'Untuk Edit']));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('Untuk Edit');
    }

    public function test_update_modifies_student(): void
    {
        $class = $this->makeClass('drawing');
        $student = Student::create($this->validPayload(['name' => 'Nama Lama']));

        $response = $this->actingAs($this->makeUser('admin'))->put(route('students.update', $student), $this->validPayload([
            'name' => 'Nama Baru',
            'status' => 'inactive',
        ]));

        $response->assertRedirect(route('students.index'));
        $student->refresh();
        $this->assertSame('Nama Baru', $student->name);
        $this->assertSame('inactive', $student->status);
        $this->assertTrue($student->classes->contains($class->id));
        $this->assertDatabaseHas('activity_logs', ['action' => 'updated']);
    }

    // ─── DESTROY (OTORISASI) ───────────────────────────────────────

    public function test_super_admin_can_delete_student(): void
    {
        $student = Student::create($this->validPayload());

        $this->actingAs($this->makeUser('super_admin'))
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deleted']);
    }

    public function test_non_super_admin_cannot_delete_student(): void
    {
        $student = Student::create($this->validPayload());

        $this->actingAs($this->makeUser('admin'))
            ->delete(route('students.destroy', $student))
            ->assertForbidden();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('students.index'))->assertRedirect(route('login'));
    }
}
