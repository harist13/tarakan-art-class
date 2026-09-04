<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'super_admin'): User
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

    public function test_dashboard_displays_1_year_growth_and_students_per_class_type(): void
    {
        $user = $this->makeUser();

        // Create students with different class types
        Student::create([
            'name' => 'Anak Preschool 1',
            'date_of_birth' => '2020-01-01',
            'parent_name' => 'Wali 1',
            'phone_number' => '081234567890',
            'class_type' => 'Preschool',
            'status' => 'active',
            'join_date' => now()->subMonths(6)->toDateString(),
        ]);

        Student::create([
            'name' => 'Anak Coloring 1',
            'date_of_birth' => '2019-01-01',
            'parent_name' => 'Wali 2',
            'phone_number' => '081234567891',
            'class_type' => 'Coloring',
            'status' => 'active',
            'join_date' => now()->subMonths(2)->toDateString(),
        ]);

        Student::create([
            'name' => 'Anak Coloring 2',
            'date_of_birth' => '2019-02-01',
            'parent_name' => 'Wali 3',
            'phone_number' => '081234567892',
            'class_type' => 'Coloring',
            'status' => 'inactive',
            'join_date' => now()->subMonths(1)->toDateString(),
        ]);

        Student::create([
            'name' => 'Anak Drawing 1',
            'date_of_birth' => '2018-01-01',
            'parent_name' => 'Wali 4',
            'phone_number' => '081234567893',
            'class_type' => 'Drawing',
            'status' => 'active',
            'join_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pertumbuhan murid (1 tahun terakhir)');
        $response->assertSee('Murid per Tipe Kelas');
        $response->assertSee('Preschool');
        $response->assertSee('Coloring');
        $response->assertSee('Drawing');

        $response->assertViewHas('growthLabels', fn ($labels) => count($labels) === 12);
        $response->assertViewHas('growthData', fn ($data) => count($data) === 12);
        $response->assertViewHas('studentsPerClassType', function ($types) {
            return isset($types['preschool'], $types['coloring'], $types['drawing'])
                && $types['preschool']['total'] === 1
                && $types['preschool']['active'] === 1
                && $types['coloring']['total'] === 2
                && $types['coloring']['active'] === 1
                && $types['coloring']['inactive'] === 1
                && $types['drawing']['total'] === 1
                && $types['drawing']['active'] === 1;
        });
    }

    /**
     * Kategori kelas berupa teks bebas, jadi panel "Murid per Tipe Kelas" harus
     * mengikuti data: kategori yang baru dibuat admin muncul walau belum ada
     * muridnya, dan kategori lama yang tak dipakai siapa pun tidak tersisa sebagai
     * baris nol permanen.
     */
    public function test_panel_tipe_kelas_mengikuti_kategori_yang_ada(): void
    {
        $user = $this->makeUser();

        $tutor = Tutor::create([
            'name' => 'Bu Sari',
            'phone_number' => '081200000000',
            'status' => 'full-time',
        ]);

        ClassRoom::create([
            'class_category' => 'Kelas Senin Sore',
            'tutor_id' => $tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->next(1)->toDateString(),
            'schedule_time' => '15:00',
            'class_fee' => 300000,
            'status' => 'open',
        ]);

        Student::create([
            'name' => 'Anak Mural',
            'date_of_birth' => '2018-01-01',
            'parent_name' => 'Wali Mural',
            'phone_number' => '081234567890',
            'class_type' => 'Kelas Mural',
            'status' => 'active',
            'join_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('studentsPerClassType', function ($types) {
            // Kategori baru tanpa murid tetap tampil (dari tabel `classes`), tipe yang
            // dipakai murid tampil dengan jumlahnya, dan enum lama sudah tidak ada.
            return isset($types['kelas senin sore'], $types['kelas mural'])
                && $types['kelas senin sore']['total'] === 0
                && $types['kelas senin sore']['label'] === 'Kelas Senin Sore'
                && $types['kelas mural']['total'] === 1
                && ! isset($types['preschool'], $types['coloring'], $types['drawing']);
        });
    }
}
