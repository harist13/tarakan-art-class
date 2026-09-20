<?php

namespace Tests\Feature;

use App\Mail\NewLeadNotification;
use App\Models\Artwork;
use App\Models\ClassRoom;
use App\Models\Lead;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('publicRoutes')]
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

    public function test_halaman_program_jatuh_ke_brosur_config_saat_belum_ada_kelas(): void
    {
        // Instalasi baru: tabel `classes` kosong, jadi yang tampil brosur config.
        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Coloring Class')
            ->assertSee('Holiday Class')
            ->assertSee('Daftar kelas ini');
    }

    public function test_kartu_program_disusun_dari_kategori_kelas_di_database(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Mewarnai', fee: 360000,
            capacity: 10, time: '13:30:00', endTime: '15:00:00');
        $this->makeClass(Carbon::today()->addDays(2), category: 'Basic Mewarnai', fee: 360000,
            capacity: 10, time: '13:30:00', endTime: '15:00:00');

        $this->get(route('public.programs'))
            ->assertOk()
            // Nama, biaya, kapasitas, durasi & jadwal dari tabel `classes`…
            ->assertSee('Basic Mewarnai')
            ->assertSee('Rp360.000 / bulan')
            ->assertSee('10 anak per kelas')
            ->assertSee('90 menit / pertemuan')
            // …usia & ringkasan tetap dari keterangan statis di config.
            ->assertSee('5 – 8 tahun', false)
            ->assertSee('Gradasi &amp; pencampuran warna', false)
            // Brosur config tidak lagi ikut tampil begitu database terisi.
            ->assertDontSee('Coloring Class');
    }

    public function test_angka_yang_berbeda_antarslot_dirangkum_jadi_rentang(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Sketch', fee: 360000,
            capacity: 6, time: '09:00:00', endTime: '10:00:00');
        $this->makeClass(Carbon::today()->addDays(2), category: 'Basic Sketch', fee: 400000,
            capacity: 10, time: '13:30:00', endTime: '15:00:00');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('6 – 10 anak per kelas', false)
            ->assertSee('60 – 90 menit / pertemuan', false)
            // Tarif yang berbeda disebut sebagai batas bawah, bukan dipilih diam-diam.
            ->assertSee('Mulai Rp360.000 / bulan');
    }

    public function test_kategori_tanpa_keterangan_memakai_teks_bawaan(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Eksperimen Clay');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Eksperimen Clay')
            ->assertSee(config('site.program_default.summary'));
    }

    public function test_pilihan_bulanan_dan_visit_tetap_ditawarkan_pada_kelas_reguler(): void
    {
        // Hanya ada kelas reguler — pilihan visit tetap harus muncul, dengan
        // harganya diserahkan ke admin karena belum ada kelas trial-nya.
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Mewarnai');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Tipe kelas')
            ->assertSee('Reguler (bulanan)')
            ->assertSee('Kelas Visit (sekali datang)')
            ->assertSee('data-visit-price="Tanyakan admin"', false);
    }

    public function test_tarif_visit_diambil_dari_kelas_trial_pada_kategori_yang_sama(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Mewarnai', fee: 360000);
        $this->makeClass(Carbon::today()->addDays(2), category: 'Basic Mewarnai', fee: 120000, type: 'trial');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('data-regular-price="Rp360.000 / bulan"', false)
            ->assertSee('data-visit-price="Rp120.000 / visit"', false);
    }

    public function test_tombol_daftar_kartu_program_membawa_kategori_kelasnya(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Mewarnai');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee(route('public.contact', ['kelas' => 'Basic Mewarnai']), false);

        // …dan tautan itu memang mempra-pilih kelasnya di form kontak.
        $this->get(route('public.contact', ['kelas' => 'Basic Mewarnai']))
            ->assertOk()
            ->assertSee('value="Basic Mewarnai" selected', false);
    }

    public function test_tabel_jadwal_umum_menyebut_hari_dan_jam_dari_database(): void
    {
        // Dua slot berbeda hari pada jam yang sama digabung jadi satu kalimat.
        $senin = Carbon::today()->next(Carbon::MONDAY);

        $this->makeClass($senin, category: 'Basic Mewarnai', time: '15:00:00', endTime: '16:30:00');
        $this->makeClass($senin->copy()->addDays(3), category: 'Basic Mewarnai', time: '15:00:00', endTime: '16:30:00');

        $this->get(route('public.schedule'))
            ->assertOk()
            ->assertSee('Senin &amp; Kamis, 15.00 WITA', false);
    }

    public function test_halaman_jadwal_menampilkan_slot_kelas_mendatang(): void
    {
        $class = $this->makeClass(Carbon::today()->addDays(3));

        $this->get(route('public.schedule'))
            ->assertOk()
            ->assertSee($class->class_category);
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
            'date_of_birth' => '2018-05-17',
            'child_age' => 7,
            'class_type' => 'coloring',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '0812 3456 7890',
            'parent_email' => 'rina@example.com',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
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

    public function test_form_kontak_menerima_pendaftaran_tanpa_usia(): void
    {
        Mail::fake();

        $this->post(route('public.contact.store'), [
            'child_name' => 'Alya Putri',
            'date_of_birth' => '2018-05-17',
            'class_type' => 'coloring',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '0812 3456 7890',
            'parent_email' => 'rina@example.com',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
            'program' => 'coloring',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'child_name' => 'Alya Putri',
            'child_age' => null,
        ]);
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
            'child_age' => 7,
            'class_type' => 'coloring',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'parent_email' => 'rina@example.com',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
            'program' => 'coloring',
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
            ->assertSee($class->class_category)
            ->assertDontSee('Coloring Class (5 – 8 tahun)', false);
    }

    public function test_kelas_dari_database_diterima_form_kontak(): void
    {
        Mail::fake();

        $class = $this->makeClass(Carbon::today()->addDay());

        $this->post(route('public.contact.store'), [
            'child_name' => 'Alya Putri',
            'date_of_birth' => '2018-05-17',
            'child_age' => 7,
            'class_type' => $class->class_category,
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'parent_email' => 'rina@example.com',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
            'program' => $class->class_category,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', ['program' => $class->class_category]);
    }

    public function test_tombol_daftar_kelas_ini_memilih_kelas_lewat_kategori(): void
    {
        $class = $this->makeClass(Carbon::today()->addDay());

        // ?kelas=coloring (slug program) → opsi kelas 'Coloring Sore' terpilih,
        // berikut tipe kelasnya yang ikut terisi.
        $this->get(route('public.contact', ['kelas' => $class->class_category]))
            ->assertOk()
            ->assertSee('value="'.$class->class_category.'" selected', false);
    }

    public function test_form_kontak_menolak_data_tidak_lengkap(): void
    {
        // Semua isian wajib kecuali usia anak & pesan.
        $this->post(route('public.contact.store'), ['child_name' => 'Alya'])
            ->assertSessionHasErrors([
                'date_of_birth', 'class_type',
                'parent_name', 'parent_phone', 'parent_email', 'address',
            ])
            ->assertSessionDoesntHaveErrors(['child_age', 'message']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_kelas_yang_diminati_tidak_wajib_bila_tipe_kelas_belum_ada_jadwalnya(): void
    {
        Mail::fake();

        // Hanya ada kelas coloring di database, calon murid memilih tipe drawing.
        $this->makeClass(Carbon::today()->addDay());

        $payload = [
            'child_name' => 'Alya Putri',
            'date_of_birth' => '2018-05-17',
            'child_age' => 7,
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'parent_email' => 'rina@example.com',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
        ];

        $this->post(route('public.contact.store'), $payload + ['class_type' => 'drawing'])
            ->assertSessionHasNoErrors();

        $this->post(route('public.contact.store'), $payload + ['class_type' => 'coloring'])
            ->assertSessionHasNoErrors();
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

    /**
     * Foto karya yang diunggah admin di modul Galeri Karya.
     */
    private function makeArtwork(string $name, string $classType, ?string $description = null): Artwork
    {
        $student = Student::create([
            'name' => $name, 'date_of_birth' => '2018-01-01', 'parent_name' => 'Wali',
            'phone_number' => '0812', 'class_type' => $classType, 'status' => 'active',
            'join_date' => now()->subYear()->toDateString(),
        ]);

        $path = 'artworks/'.uniqid().'.jpg';
        Storage::disk('public')->put($path, 'foto');

        return Artwork::create([
            'student_id' => $student->id,
            'photo_path' => $path,
            'taken_on' => now()->toDateString(),
            'description' => $description,
        ]);
    }

    public function test_karya_dari_modul_galeri_karya_tampil_di_beranda_dan_galeri(): void
    {
        Storage::fake('public');

        $karya = $this->makeArtwork('Bella Safira', 'drawing', 'Pemandangan sore');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee($karya->photoUrl(), false)
            ->assertSee('Pemandangan sore');

        $this->get(route('public.gallery'))
            ->assertOk()
            ->assertSee($karya->photoUrl(), false)
            ->assertSee('Pemandangan sore');
    }

    public function test_karya_tanpa_deskripsi_hanya_menyebut_nama_depan_murid(): void
    {
        Storage::fake('public');

        $this->makeArtwork('Bella Safira', 'drawing');

        $this->get(route('public.gallery'))
            ->assertOk()
            ->assertSee('Karya Bella')
            ->assertDontSee('Safira');
    }

    public function test_filter_kategori_galeri_mengikuti_tipe_kelas_murid(): void
    {
        Storage::fake('public');

        $drawing = $this->makeArtwork('Bella', 'drawing', 'Sketsa rumah');
        $coloring = $this->makeArtwork('Cika', 'coloring', 'Mewarnai bunga');

        $this->get(route('public.gallery', ['kategori' => 'drawing']))
            ->assertOk()
            ->assertSee($drawing->photoUrl(), false)
            ->assertDontSee($coloring->photoUrl(), false);

        // Kategori tanpa satu pun karya tidak ditawarkan sebagai tombol filter.
        $this->get(route('public.gallery'))
            ->assertOk()
            ->assertDontSee(route('public.gallery', ['kategori' => 'preschool']), false);
    }

    public function test_galeri_publik_terbagi_halaman_saat_karya_menumpuk(): void
    {
        Storage::fake('public');

        $karya = $this->makeArtwork('Bella', 'drawing', 'Karya ke-1');

        // 24 karya per halaman: satu lagi sudah cukup untuk menggeser yang terlama.
        for ($i = 2; $i <= 25; $i++) {
            $path = 'artworks/'.uniqid().'.jpg';
            Storage::disk('public')->put($path, 'foto');
            Artwork::create([
                'student_id' => $karya->student_id,
                'photo_path' => $path,
                'taken_on' => now()->toDateString(),
                'description' => 'Karya ke-'.$i,
            ]);
        }

        $this->get(route('public.gallery'))
            ->assertOk()
            ->assertSee('Karya ke-25')
            ->assertSee(route('public.gallery').'?page=2', false);

        // Karya terlama terdorong ke halaman kedua.
        $this->get(route('public.gallery', ['page' => 2]))
            ->assertOk()
            ->assertSee('Karya ke-1')
            ->assertDontSee('Karya ke-25');
    }

    public function test_foto_karya_yang_berkasnya_hilang_tidak_ditampilkan(): void
    {
        Storage::fake('public');

        $karya = $this->makeArtwork('Bella', 'drawing', 'Sketsa rumah');
        Storage::disk('public')->delete($karya->photo_path);

        $this->get(route('public.gallery'))
            ->assertOk()
            ->assertDontSee($karya->photoUrl(), false);
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

    public function test_navbar_tidak_memuat_menu_jadwal(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('<a class="nav-link tac-nav-link " href="'.route('public.schedule').'"', false)
            ->assertSee('Program')
            ->assertSee('Galeri')
            ->assertSee('Kontak');
    }

    public function test_card_program_memuat_dropdown_tipe_kelas(): void
    {
        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Tipe kelas')
            ->assertSee('Reguler (bulanan)')
            ->assertSee('Kelas Visit (sekali datang)')
            ->assertSee('Rp115.000 / visit')
            ->assertSee('Rp105.000 / visit');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Tipe kelas')
            ->assertSee('Reguler (bulanan)')
            ->assertSee('Kelas Visit (sekali datang)');
    }

    public function test_halaman_kontak_memuat_faq_dropdown(): void
    {
        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('Pertanyaan yang sering ditanyakan')
            ->assertSee('tac-faq', false)
            ->assertSee('Apakah anak saya harus sudah bisa menggambar?');
    }

    public function test_halaman_beranda_memuat_section_pengumuman(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Pengumuman & agenda terkini', false);
    }

    private function makeClass(
        Carbon $date,
        int $capacity = 8,
        string $category = 'coloring',
        float $fee = 275000,
        string $type = 'regular',
        string $time = '15:00:00',
        ?string $endTime = null,
    ): ClassRoom {
        $tutor = Tutor::firstOrCreate(['name' => 'Kak Ayu'], ['status' => 'full-time']);

        return ClassRoom::create([
            'class_category' => $category,
            'tutor_id' => $tutor->id,
            'capacity' => $capacity,
            // Kelas mingguan yang sesi pertamanya jatuh pada $date — penelepon
            // memakainya sebagai "kelas pada tanggal ini".
            'schedule_date' => $date,
            'schedule_time' => $time,
            'schedule_end_time' => $endTime,
            'class_type' => $type,
            'is_recurring' => $type === 'regular',
            'class_fee' => $fee,
            'status' => 'open',
        ]);
    }
}
