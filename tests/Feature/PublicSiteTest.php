<?php

namespace Tests\Feature;

use App\Mail\NewLeadNotification;
use App\Models\ClassRoom;
use App\Models\Holiday;
use App\Models\Lead;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    public static function publicRoutes(): array
    {
        return [
            ['public.home'],
            ['public.about'],
            ['public.programs'],
            ['public.gallery'],
            ['public.schedule'],
            ['public.contact'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicRoutes')]
    public function test_halaman_publik_bisa_diakses_tanpa_login(string $routeName): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('Tarakan Art Class', false);
    }

    public function test_dashboard_pindah_ke_slash_dashboard_dan_tetap_butuh_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));

        $user = User::create([
            'full_name' => 'Admin Uji',
            'email' => 'admin@example.com',
            'username' => 'adminuji',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_halaman_program_menampilkan_kelas_dari_config(): void
    {
        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Coloring Class')
            ->assertSee('Holiday Class')
            ->assertSee('Daftar kelas ini');
    }

    public function test_halaman_jadwal_menampilkan_slot_kelas_mendatang(): void
    {
        $class = $this->makeClass(Carbon::today()->addDays(3));

        Holiday::create(['date' => Carbon::today()->addDays(5), 'name' => 'Libur Nasional']);

        $this->get(route('public.schedule'))
            ->assertOk()
            ->assertSee($class->class_name)
            ->assertSee('Libur Nasional');
    }

    public function test_kartu_program_menampilkan_sisa_kursi_dari_database(): void
    {
        $this->makeClass(Carbon::today()->addDay(), capacity: 8);

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('8 kursi tersisa');
    }

    public function test_form_kontak_menyimpan_lead_dan_mengirim_notifikasi(): void
    {
        Mail::fake();

        $response = $this->post(route('public.contact.store'), [
            'child_name' => 'Alya Putri',
            'child_age' => 7,
            'parent_name' => 'Bu Rina',
            'parent_phone' => '0812 3456 7890',
            'parent_email' => 'rina@example.com',
            'program' => 'coloring',
            'message' => 'Anak saya belum pernah ikut kelas seni.',
        ]);

        $response->assertRedirect(route('public.contact'))
            ->assertSessionHas('lead_sent', 'Alya Putri');

        $this->assertDatabaseHas('leads', [
            'child_name' => 'Alya Putri',
            'program' => 'coloring',
            'status' => 'new',
        ]);

        Mail::assertSent(NewLeadNotification::class);
    }

    public function test_email_notifikasi_lead_bisa_dirender(): void
    {
        // Mail::fake() tidak merender view, jadi isi email diuji terpisah.
        $lead = Lead::create([
            'child_name' => 'Alya Putri',
            'child_age' => 7,
            'parent_name' => 'Bu Rina',
            'parent_phone' => '0812 3456 7890',
            'program' => 'coloring',
            'message' => 'Titip anak saya ya.',
        ]);

        $rendered = (new NewLeadNotification($lead))->render();

        $this->assertStringContainsString('Alya Putri', $rendered);
        $this->assertStringContainsString('Coloring Class', $rendered);
        // Nomor lokal 0812… harus jadi 62812… pada tautan wa.me.
        $this->assertStringContainsString('wa.me/6281234567890', $rendered);
    }

    public function test_form_kontak_menyimpan_detail_anak_tambahan(): void
    {
        Mail::fake();

        $this->post(route('public.contact.store'), [
            'child_name' => 'Alya Putri',
            'date_of_birth' => '2018-05-17',
            'class_type' => 'coloring',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'child_name' => 'Alya Putri',
            'class_type' => 'coloring',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
        ]);

        $this->assertSame('2018-05-17', Lead::first()->date_of_birth->toDateString());
    }

    public function test_form_kontak_menolak_tipe_kelas_dan_tanggal_lahir_tidak_valid(): void
    {
        $this->post(route('public.contact.store'), [
            'child_name' => 'Alya',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'class_type' => 'melukis-mural',
            'date_of_birth' => Carbon::tomorrow()->toDateString(),
        ])->assertSessionHasErrors(['class_type', 'date_of_birth']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_dropdown_kelas_diambil_dari_database(): void
    {
        $class = $this->makeClass(Carbon::today()->addDay());

        // Nama kelas dari database menggantikan daftar program di config.
        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee($class->class_name)
            ->assertDontSee('Coloring Class (5 – 8 tahun)', false);
    }

    public function test_kelas_dari_database_diterima_form_kontak(): void
    {
        Mail::fake();

        $class = $this->makeClass(Carbon::today()->addDay());

        $this->post(route('public.contact.store'), [
            'child_name' => 'Alya Putri',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'program' => $class->class_name,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', ['program' => $class->class_name]);
    }

    public function test_tombol_daftar_kelas_ini_memilih_kelas_lewat_kategori(): void
    {
        $class = $this->makeClass(Carbon::today()->addDay());

        // ?kelas=coloring (slug program) → opsi kelas 'Coloring Sore' terpilih.
        $this->get(route('public.contact', ['kelas' => $class->class_category]))
            ->assertOk()
            ->assertSee('value="'.$class->class_name.'" selected', false);
    }

    public function test_form_kontak_menolak_data_tidak_lengkap(): void
    {
        $this->post(route('public.contact.store'), ['child_name' => 'Alya'])
            ->assertSessionHasErrors(['parent_name', 'parent_phone']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_honeypot_memblokir_kiriman_bot(): void
    {
        Mail::fake();

        $this->post(route('public.contact.store'), [
            'child_name' => 'Bot',
            'parent_name' => 'Bot',
            'parent_phone' => '0812345678',
            'website' => 'http://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('leads', 0);
        Mail::assertNothingSent();
    }

    public function test_program_yang_tidak_dikenal_ditolak(): void
    {
        $this->post(route('public.contact.store'), [
            'child_name' => 'Alya',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'program' => 'kelas-palsu',
        ])->assertSessionHasErrors('program');
    }

    public function test_filter_galeri_yang_tidak_valid_diabaikan(): void
    {
        $this->get(route('public.gallery', ['kategori' => '<script>']))->assertOk();
    }

    public function test_sitemap_memuat_seluruh_halaman_publik(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertHeader('Content-Type', 'application/xml');

        foreach (array_column(self::publicRoutes(), 0) as $routeName) {
            $response->assertSee(route($routeName), false);
        }
    }

    public function test_lead_mengembalikan_nama_program_yang_terbaca(): void
    {
        $lead = Lead::create([
            'child_name' => 'Alya',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'program' => 'drawing',
        ]);

        $this->assertSame('Drawing Class', $lead->programName());
    }

    private function makeClass(Carbon $date, int $capacity = 8): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak Ayu', 'status' => 'active']);

        return ClassRoom::create([
            'class_name' => 'Coloring Sore',
            'class_category' => 'coloring',
            'tutor_id' => $tutor->id,
            'capacity' => $capacity,
            'schedule_date' => $date,
            'schedule_time' => '15:00:00',
            'class_fee' => 275000,
            'status' => 'open',
        ]);
    }
}
