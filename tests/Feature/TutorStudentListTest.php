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
 * Panel Manajemen Tutor menghitung murid, bukan kelas: pertanyaannya
 * "Kak Sari pegang berapa anak, siapa saja", dan jumlah kelas tidak
 * menjawabnya.
 */
class TutorStudentListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
    }

    private function makeClass(Tutor $tutor, string $category): ClassRoom
    {
        return ClassRoom::create([
            'class_category' => $category,
            'tutor_id' => $tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'is_recurring' => true,
            'class_fee' => 250000,
        ]);
    }

    private function makeStudent(string $name, string $type): Student
    {
        return Student::create([
            'name' => $name,
            'date_of_birth' => '2018-01-01',
            'parent_name' => 'Wali '.$name,
            'phone_number' => '081200000000',
            'class_type' => $type,
            'status' => 'active',
        ]);
    }

    private function enroll(Student $student, ClassRoom $class, string $status = 'active'): void
    {
        $student->classes()->attach($class->id, ['status' => $status, 'enrolled_at' => now()]);
    }

    public function test_tutor_panel_lists_students_handled_by_each_tutor(): void
    {
        $tutor = Tutor::create(['name' => 'Kak Sari', 'status' => 'part-time']);
        $coloring = $this->makeClass($tutor, 'Coloring');

        $andi = $this->makeStudent('Andi', 'Coloring');
        $budi = $this->makeStudent('Budi', 'Coloring');
        $this->enroll($andi, $coloring);
        $this->enroll($budi, $coloring);

        $response = $this->actingAs($this->admin)->get(route('classes.index', ['tab' => 'tutor']));

        $response->assertOk()
            ->assertSee('2 murid')
            ->assertSee('Andi')
            ->assertSee('Budi');
    }

    public function test_student_in_two_classes_of_same_tutor_is_counted_once(): void
    {
        $tutor = Tutor::create(['name' => 'Kak Rina', 'status' => 'full-time']);
        $andi = $this->makeStudent('Andi', 'Coloring');

        $this->enroll($andi, $this->makeClass($tutor, 'Coloring'));
        $this->enroll($andi, $this->makeClass($tutor, 'Drawing'));

        $this->assertSame(1, $tutor->activeStudentCount());
    }

    public function test_inactive_enrollment_is_not_counted(): void
    {
        $tutor = Tutor::create(['name' => 'Kak Bagus', 'status' => 'part-time']);
        $class = $this->makeClass($tutor, 'Preschool');

        $this->enroll($this->makeStudent('Andi', 'Preschool'), $class);
        $this->enroll($this->makeStudent('Citra', 'Preschool'), $class, 'inactive');

        $this->assertSame(1, $tutor->activeStudentCount());
    }
}
