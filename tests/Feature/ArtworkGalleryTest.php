<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: Galeri Karya — arsip foto karya murid.
 *
 * Folder bukan baris di database melainkan pengelompokan (murid × bulan) dari
 * `taken_on`, dan isinya menyambung ke raport bulan yang sama lewat periodenya —
 * itulah dua hal yang paling perlu dijaga di sini.
 */
class ArtworkGalleryTest extends TestCase
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

    private function makeStudent(string $name = 'Bella Safira'): Student
    {
        $tutor = Tutor::create(['name' => 'Kak T', 'status' => 'full-time']);
        ClassRoom::create([
            'class_category' => 'drawing', 'tutor_id' => $tutor->id,
            'capacity' => 5, 'schedule_date' => now()->toDateString(), 'schedule_time' => '09:00',
            'class_fee' => 100000,
        ]);

        $student = Student::create([
            'name' => $name, 'date_of_birth' => '2018-01-01', 'parent_name' => 'Wali',
            'phone_number' => '0812', 'class_type' => 'drawing', 'status' => 'active',
            'join_date' => now()->subYear()->toDateString(),
        ]);

        // Tanpa tunggakan, agar raportnya bisa dibuka orang tua.
        Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'payment_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        return $student;
    }

    private function makeArtwork(Student $student, string $date, ?string $description = null): Artwork
    {
        return Artwork::create([
            'student_id' => $student->id,
            'photo_path' => 'artworks/'.uniqid().'.jpg',
            'taken_on' => $date,
            'description' => $description,
        ]);
    }

    public function test_admin_mengunggah_beberapa_foto_sekaligus(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();
        $tanggal = now()->subDays(3)->toDateString();

        $this->actingAs($this->admin())->post(route('artworks.store'), [
            'student_id' => $student->id,
            'taken_on' => $tanggal,
            'description' => 'Melukis tema laut',
            'photos' => [
                UploadedFile::fake()->image('karya1.jpg'),
                UploadedFile::fake()->image('karya2.jpg'),
            ],
        ])->assertRedirect(route('artworks.folder', [
            'student' => $student->id,
            'month' => now()->subDays(3)->format('Y-m'),
        ]));

        $this->assertDatabaseCount('artworks', 2);

        foreach (Artwork::all() as $artwork) {
            Storage::disk('public')->assertExists($artwork->photo_path);
            $this->assertSame('Melukis tema laut', $artwork->description);
            $this->assertSame($tanggal, $artwork->taken_on->toDateString());
        }
    }

    public function test_berkas_bukan_gambar_ditolak_dan_tak_ada_yang_tersimpan(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();

        $this->actingAs($this->admin())->post(route('artworks.store'), [
            'student_id' => $student->id,
            'taken_on' => now()->toDateString(),
            'photos' => [UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf')],
        ])->assertSessionHasErrors('photos.0');

        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_tanggal_karya_di_masa_depan_ditolak(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();

        $this->actingAs($this->admin())->post(route('artworks.store'), [
            'student_id' => $student->id,
            'taken_on' => now()->addDay()->toDateString(),
            'photos' => [UploadedFile::fake()->image('karya.jpg')],
        ])->assertSessionHasErrors('taken_on');

        $this->assertDatabaseCount('artworks', 0);
    }

    /**
     * Folder diturunkan dari tanggal, bukan dibuat manual — dua karya di bulan
     * berbeda harus jatuh ke dua folder berbeda tanpa admin mengatur apa pun.
     */
    public function test_folder_bulan_dikelompokkan_dari_tanggal_karya(): void
    {
        $student = $this->makeStudent();
        $this->makeArtwork($student, '2026-09-05');
        $this->makeArtwork($student, '2026-09-20');
        $this->makeArtwork($student, '2026-08-11');

        $this->actingAs($this->admin())->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('September 2026')
            ->assertSee('Agustus 2026');

        // Folder September berisi dua karya, folder Agustus tidak ikut terbawa.
        $this->assertCount(2, $student->artworks()->inMonth('2026-09')->get());
        $this->assertCount(1, $student->artworks()->inMonth('2026-08')->get());
    }

    public function test_folder_murid_hanya_menampilkan_karya_bulan_itu(): void
    {
        $student = $this->makeStudent();
        $this->makeArtwork($student, '2026-09-05', 'Karya September');
        $this->makeArtwork($student, '2026-08-11', 'Karya Agustus');

        $this->actingAs($this->admin())
            ->get(route('artworks.folder', ['student' => $student->id, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('Karya September')
            ->assertDontSee('Karya Agustus');
    }

    public function test_bulan_dengan_format_salah_menghasilkan_404(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($this->admin())
            ->get('/galeri-karya/'.$student->id.'/2026-9')
            ->assertNotFound();
    }

    /**
     * Mengubah tanggal memindahkan foto ke folder bulan lain — itu sebabnya
     * tanggal ikut bisa diedit, bukan cuma deskripsinya.
     */
    public function test_mengubah_tanggal_memindahkan_karya_ke_folder_lain(): void
    {
        $student = $this->makeStudent();
        $artwork = $this->makeArtwork($student, '2026-09-05', 'Lama');

        $this->actingAs($this->admin())->put(route('artworks.update', $artwork), [
            'taken_on' => '2026-08-05',
            'description' => 'Baru',
        ])->assertRedirect(route('artworks.folder', ['student' => $student->id, 'month' => '2026-08']));

        $artwork->refresh();
        $this->assertSame('2026-08', $artwork->month());
        $this->assertSame('Baru', $artwork->description);
    }

    public function test_menghapus_karya_ikut_menghapus_berkasnya(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('artworks.store'), [
            'student_id' => $student->id,
            'taken_on' => now()->toDateString(),
            'photos' => [UploadedFile::fake()->image('karya.jpg')],
        ])->assertRedirect();

        $artwork = Artwork::firstOrFail();
        $path = $artwork->photo_path;

        $this->actingAs($admin)->delete(route('artworks.destroy', $artwork))->assertRedirect();

        $this->assertDatabaseCount('artworks', 0);
        Storage::disk('public')->assertMissing($path);
    }

    // ─── Sambungan ke raport & credential key ────────────────────

    /**
     * Karya diikat ke raport lewat rentang periodenya, bukan foreign key — jadi
     * karya yang diunggah sebelum raportnya dibuat pun tetap ikut terbawa.
     */
    public function test_orang_tua_melihat_karya_periode_raport_lewat_credential_key(): void
    {
        $student = $this->makeStudent();
        $this->makeArtwork($student, '2026-09-05', 'Karya di dalam periode');
        $this->makeArtwork($student, '2026-10-05', 'Karya di luar periode');

        $report = StudentReport::create([
            'student_id' => $student->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'activity_notes' => 'Berkembang baik',
        ]);

        $this->post(route('reports.guest.show'), ['credential_key' => $report->credential_key])
            ->assertOk()
            ->assertSee('Karya di dalam periode')
            ->assertDontSee('Karya di luar periode');
    }

    /**
     * Karya menumpang pintu yang sama dengan raportnya: kalau raport tertahan
     * karena tunggakan, galerinya tidak boleh bocor lewat celah lain.
     */
    public function test_karya_ikut_tertahan_saat_murid_menunggak(): void
    {
        $student = $this->makeStudent();
        Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->subMonths(2)->toDateString(),
            'due_date' => now()->subMonth()->toDateString(),
            'payment_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $this->makeArtwork($student, '2026-09-05', 'Karya Rahasia');

        $report = StudentReport::create([
            'student_id' => $student->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'activity_notes' => 'Berkembang baik',
        ]);

        $this->post(route('reports.guest.show'), ['credential_key' => $report->credential_key])
            ->assertSessionHasErrors('credential_key');

        $this->get(route('reports.guest'))->assertOk()->assertDontSee('Karya Rahasia');
    }

    public function test_halaman_bulan_raport_menautkan_folder_karya(): void
    {
        $student = $this->makeStudent();
        $this->makeArtwork($student, '2026-09-05');

        StudentReport::create([
            'student_id' => $student->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'activity_notes' => 'Berkembang baik',
        ]);

        $this->actingAs($this->admin())->get(route('reports.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee(route('artworks.folder', ['student' => $student->id, 'month' => '2026-09']), false);
    }

    public function test_folder_menampilkan_credential_key_raport_bulan_yang_sama(): void
    {
        $student = $this->makeStudent();
        $this->makeArtwork($student, '2026-09-05');

        $report = StudentReport::create([
            'student_id' => $student->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'activity_notes' => 'Berkembang baik',
        ]);

        $this->actingAs($this->admin())
            ->get(route('artworks.folder', ['student' => $student->id, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee($report->credential_key);
    }

    public function test_folder_tanpa_raport_mengingatkan_admin(): void
    {
        $student = $this->makeStudent();
        $this->makeArtwork($student, '2026-09-05');

        $this->actingAs($this->admin())
            ->get(route('artworks.folder', ['student' => $student->id, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('Belum ada raport');
    }

    public function test_tamu_tanpa_login_tidak_bisa_membuka_galeri_admin(): void
    {
        $student = $this->makeStudent();

        $this->get(route('artworks.index'))->assertRedirect(route('login'));
        $this->get(route('artworks.folder', ['student' => $student->id, 'month' => '2026-09']))
            ->assertRedirect(route('login'));
    }
}
