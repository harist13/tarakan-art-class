<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeSketchingClassesTest extends TestCase
{
    use RefreshDatabase;

    private function makeClass(string $category, string $date, string $start, string $end): ClassRoom
    {
        return ClassRoom::create([
            'class_category' => $category,
            'capacity' => 10,
            'schedule_date' => $date,
            'schedule_time' => $start,
            'schedule_end_time' => $end,
            'class_fee' => 360000,
            'status' => 'open',
        ]);
    }

    private function makeStudent(string $name, string $type, ClassRoom $class): Student
    {
        $student = Student::create([
            'name' => $name,
            'parent_name' => 'Ibu '.$name,
            'phone_number' => '081234567890',
            'class_type' => $type,
            'status' => 'active',
            'join_date' => '2026-09-01',
        ]);
        $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => '2026-09-01']);

        return $student;
    }

    private function runMigration(): void
    {
        (require database_path('migrations/2026_09_25_000000_merge_sketching_classes_into_basic_sketch.php'))->up();
    }

    public function test_sketching_dilebur_ke_basic_sketch_di_jadwal_yang_sama(): void
    {
        // 2026-09-26 & 2026-10-03 sama-sama Sabtu.
        $basic = $this->makeClass('Basic Sketch', '2026-09-26', '12:30', '14:00');
        $sketching = $this->makeClass('Sketching', '2026-10-03', '12:30', '14:00');

        $lama = $this->makeStudent('Reinhart', 'Basic Sketch', $basic);
        $pindah = $this->makeStudent('Edward', 'Sketching', $sketching);
        Attendance::create([
            'student_id' => $pindah->id,
            'class_id' => $sketching->id,
            'attendance_date' => '2026-09-19',
            'status' => 'present',
        ]);

        $this->runMigration();

        $this->assertNull(ClassRoom::find($sketching->id));
        $this->assertEqualsCanonicalizing(
            [$lama->id, $pindah->id],
            $basic->fresh()->students->pluck('id')->all()
        );
        $this->assertSame('Basic Sketch', $pindah->fresh()->class_type);
        $this->assertTrue(Attendance::where('student_id', $pindah->id)->where('class_id', $basic->id)->exists());
    }

    public function test_sketching_tanpa_kembaran_cukup_diganti_kategorinya(): void
    {
        $this->makeClass('Basic Sketch', '2026-09-26', '09:30', '11:00');
        $sketching = $this->makeClass('Sketching', '2026-09-26', '15:30', '17:00');
        $murid = $this->makeStudent('Issa', 'Sketching', $sketching);

        $this->runMigration();

        $this->assertSame('Basic Sketch', $sketching->fresh()->class_category);
        $this->assertTrue($sketching->fresh()->students->contains($murid));
        $this->assertSame(0, ClassRoom::where('class_category', 'Sketching')->count());
    }
}
