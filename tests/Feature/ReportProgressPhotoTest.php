<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: foto progres minggu pertama & terakhir di raport — terpisah dari Galeri
 * Karya, yang khusus karya selesai.
 */
class ReportProgressPhotoTest extends TestCase
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

    private function makeStudent(): Student
    {
        $student = Student::create([
            'name' => 'Kharen', 'date_of_birth' => '2018-01-01', 'parent_name' => 'Wali',
            'phone_number' => '081234567890', 'class_type' => 'Basic Sketch', 'status' => 'active',
            'join_date' => now()->subYear()->toDateString(),
        ]);

        // Tanpa tunggakan, agar raportnya bisa dibuka orang tua.
        Payment::create([
            'student_id' => $student->id, 'payment_date' => now()->toDateString(),
            'payment_amount' => 100000, 'payment_method' => 'cash', 'payment_status' => 'paid',
        ]);

        return $student;
    }

    private function payload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $student->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'activity_notes' => 'Belajar blending.',
        ], $overrides);
    }

    public function test_foto_progres_tersimpan_dan_tampil_untuk_orang_tua(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();

        $this->actingAs($this->admin())->post(route('reports.store'), $this->payload($student, [
            'progress_first_photo' => UploadedFile::fake()->image('minggu1.jpg'),
            'progress_first_caption' => 'Blending kepala',
            'progress_last_photo' => UploadedFile::fake()->image('minggu4.jpg'),
            'progress_last_caption' => 'Blending badan',
        ]))->assertSessionHasNoErrors();

        $report = StudentReport::sole();
        Storage::disk('public')->assertExists($report->progress_first_photo);
        Storage::disk('public')->assertExists($report->progress_last_photo);

        $this->get(route('reports.show', $report))
            ->assertOk()
            ->assertSeeInOrder(['Progres bulan ini', 'Minggu pertama', 'Blending kepala', 'Karya selesai periode ini']);
        $this->get(route('reports.edit', $report))
            ->assertOk()
            ->assertSee('Hapus foto ini')
            ->assertSee('value="Blending badan"', false);

        $this->post(route('reports.guest.show'), ['credential_key' => $report->credential_key])
            ->assertOk()
            ->assertSeeInOrder(['Progres bulan ini', 'Minggu pertama', 'Blending kepala', 'Minggu terakhir', 'Blending badan']);
    }

    public function test_foto_progres_opsional(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($this->admin())->post(route('reports.store'), $this->payload($student))
            ->assertSessionHasNoErrors();

        $report = StudentReport::sole();
        $this->assertSame([], $report->progressPhotos());

        $this->post(route('reports.guest.show'), ['credential_key' => $report->credential_key])
            ->assertOk()
            ->assertDontSee('Progres bulan ini');
    }

    public function test_mengganti_dan_menghapus_foto_membuang_berkas_lama(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('reports.store'), $this->payload($student, [
            'progress_first_photo' => UploadedFile::fake()->image('a.jpg'),
            'progress_last_photo' => UploadedFile::fake()->image('b.jpg'),
            'progress_last_caption' => 'Blending badan',
        ]));
        $report = StudentReport::sole();
        [$lamaPertama, $lamaTerakhir] = [$report->progress_first_photo, $report->progress_last_photo];

        // Ganti foto pertama, hapus foto terakhir.
        $this->actingAs($admin)->put(route('reports.update', $report), $this->payload($student, [
            'progress_first_photo' => UploadedFile::fake()->image('c.jpg'),
            'remove_progress_last_photo' => '1',
        ]))->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertNotSame($lamaPertama, $report->progress_first_photo);
        Storage::disk('public')->assertExists($report->progress_first_photo);
        Storage::disk('public')->assertMissing($lamaPertama);
        Storage::disk('public')->assertMissing($lamaTerakhir);
        $this->assertNull($report->progress_last_photo);
        $this->assertNull($report->progress_last_caption);

        // Menghapus raport ikut membuang berkasnya.
        $sisa = $report->progress_first_photo;
        $this->actingAs($admin)->delete(route('reports.destroy', $report));
        Storage::disk('public')->assertMissing($sisa);
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();

        $this->actingAs($this->admin())->post(route('reports.store'), $this->payload($student, [
            'progress_first_photo' => UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf'),
        ]))->assertSessionHasErrors('progress_first_photo');

        $this->assertSame(0, StudentReport::count());
    }
}
