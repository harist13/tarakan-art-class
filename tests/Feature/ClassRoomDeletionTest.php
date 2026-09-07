<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kelas yang masih memegang murid, absensi, atau replacement tidak boleh
 * terhapus: kunci asingnya memakai cascade, jadi penghapusan yang lolos akan
 * membawa serta riwayat yang tak bisa dipulihkan.
 */
class ClassRoomDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tutor $tutor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        $this->admin = User::create([
            'full_name' => 'Admin Test',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->admin->assignRole('admin');

        $this->tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'full-time']);
    }

    private function makeClass(string $category = 'Coloring'): ClassRoom
    {
        return ClassRoom::create([
            'class_category' => $category,
            'tutor_id' => $this->tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'is_recurring' => true,
            'class_fee' => 150000,
        ]);
    }

    public function test_empty_class_is_deleted_and_returns_to_the_page_it_came_from(): void
    {
        $class = $this->makeClass();

        $response = $this->actingAs($this->admin)
            ->from(route('schedules.calendar'))
            ->delete(route('classes.destroy', $class));

        $response->assertRedirect(route('schedules.calendar'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('classes', ['id' => $class->id]);
    }

    public function test_class_with_students_cannot_be_deleted(): void
    {
        $class = $this->makeClass();
        $student = Student::create([
            'name' => 'Bella Safira',
            'date_of_birth' => now()->subYears(8)->toDateString(),
            'age' => 8,
            'parent_name' => 'Orang Tua Bella',
            'phone_number' => '081200000001',
            'address' => 'Tarakan',
            'class_type' => 'Coloring',
            'status' => 'active',
            'join_date' => now()->toDateString(),
        ]);
        $class->students()->attach($student->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($this->admin)
            ->from(route('classes.index'))
            ->delete(route('classes.destroy', $class));

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'Maaf')
            && str_contains($pesan, '1 murid terdaftar'));
        $this->assertDatabaseHas('classes', ['id' => $class->id]);
        // Murid & pendaftarannya tetap utuh — tidak ada cascade yang terpicu.
        $this->assertDatabaseHas('student_class', ['class_id' => $class->id, 'student_id' => $student->id]);
    }
}
