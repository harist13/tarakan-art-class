<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin']);
        $user = User::create([
            'full_name' => 'Tester',
            'email' => 'tester@example.com',
            'username' => 'tester',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function makeStudent(): Student
    {
        $tutor = Tutor::create(['name' => 'Kak T', 'status' => 'active']);
        ClassRoom::create([
            'class_name' => 'Kelas Uji', 'class_category' => 'drawing', 'tutor_id' => $tutor->id,
            'capacity' => 5, 'schedule_date' => now()->toDateString(), 'schedule_time' => '09:00',
            'class_fee' => 100000,
        ]);

        return Student::create([
            'name' => 'Murid Uji', 'date_of_birth' => '2018-01-01', 'parent_name' => 'Wali',
            'phone_number' => '0812', 'class_type' => 'drawing', 'status' => 'active',
            'join_date' => now()->toDateString(),
        ]);
    }

    public function test_report_stores_uploaded_photo(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();

        $response = $this->actingAs($this->superAdmin())->post('/reports', [
            'student_id' => $student->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'activity_notes' => 'Perkembangan bagus',
            'achievement_score' => 90,
            'photo' => UploadedFile::fake()->image('pas.jpg', 400, 600),
        ]);

        $response->assertRedirect(route('reports.index'));

        $report = StudentReport::first();
        $this->assertNotNull($report->photo_path, 'photo_path harus terisi');
        Storage::disk('public')->assertExists($report->photo_path);
        $this->assertStringContainsString('storage/'.$report->photo_path, $report->photoUrl());
    }

    public function test_report_rejects_non_image(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();

        $response = $this->actingAs($this->superAdmin())->post('/reports', [
            'student_id' => $student->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'activity_notes' => 'Test',
            'achievement_score' => 80,
            'photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('student_reports', 0);
    }
}
