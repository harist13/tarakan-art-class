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

    public function test_halaman_program_menampilkan_empat_program_tetap(): void
    {
        // Instalasi baru: tabel `classes` kosong, keempat program tetap tampil
        // dengan angka cadangan dari config.
        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Preschool')
            ->assertSee('Sketching')
            ->assertSee('Coloring')
            ->assertSee('Holiday Class')
            ->assertSee('Daftar kelas ini');
    }

    public function test_kartu_program_disusun_dari_kategori_kelas_di_database(): void
    {
        // Preschool: Sketching & Coloring memakai teks kapasitas/jadwal tetap.
        $this->makeClass(Carbon::today()->addDay(), category: 'Pre-school', fee: 360000,
            capacity: 10, time: '13:30:00', endTime: '15:00:00');
        $this->makeClass(Carbon::today()->addDays(2), category: 'Pre-school', fee: 360000,
            capacity: 10, time: '13:30:00', endTime: '15:00:00');

        $this->get(route('public.programs'))
            ->assertOk()
            // Biaya, kapasitas, durasi & jadwal dari tabel `classes`…
            ->assertSee('Rp360.000 / bulan')
            ->assertSee('10 anak per kelas')
            ->assertSee('90 menit / pertemuan')
            // …usia & ringkasan tetap dari keterangan statis di config.
            ->assertSee('2,5 – 3 tahun', false)
            ->assertSee('Finger painting &amp; kolase', false)
            // Kartunya bernama program, bukan kategori kelas di database.
            ->assertSee('Preschool')
            ->assertDontSee('Pre-school');
    }

    public function test_angka_yang_berbeda_antarslot_dirangkum_jadi_rentang(): void
    {
        // Bukan Sketching/Coloring: kapasitasnya teks tetap di config (`capacity_label`).
        $this->makeClass(Carbon::today()->addDay(), category: 'Preschool', fee: 360000,
            capacity: 6, time: '09:00:00', endTime: '10:00:00');
        $this->makeClass(Carbon::today()->addDays(2), category: 'Preschool', fee: 400000,
            capacity: 10, time: '13:30:00', endTime: '15:00:00');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('6 – 10 anak per kelas', false)
            ->assertSee('60 – 90 menit / pertemuan', false)
            // Tarif yang berbeda disebut sebagai batas bawah, bukan dipilih diam-diam.
            ->assertSee('Mulai Rp360.000 / bulan');
    }

    public function test_teks_kapasitas_dan_jadwal_dari_config_menang_atas_slot(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Sketch', capacity: 7);

        foreach (['public.programs', 'public.home'] as $page) {
            $this->get(route($page))
                ->assertOk()
                ->assertSee('1 tutor max 3–4 anak', false)
                ->assertSee('Senin – Sabtu', false)
                ->assertDontSee('7 anak per kelas', false);
        }
    }

    public function test_kategori_di_luar_empat_program_tidak_tampil(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Eksperimen Clay');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertDontSee('Eksperimen Clay');
    }

    public function test_beberapa_kategori_kelas_digabung_ke_satu_program(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Sketch', fee: 360000);
        $this->makeClass(Carbon::today()->addDays(2), category: 'Character', fee: 400000);

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Mulai Rp360.000 / bulan')
            ->assertDontSee('Character');
    }

    public function test_pilihan_bulanan_dan_visit_ditawarkan_dengan_tarif_visit_tetap(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Pre-school');
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Sketch');
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Mewarnai');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Reguler (bulanan)')
            ->assertSee('Kelas Visit (sekali datang)')
            ->assertSee('data-visit-price="Rp115.000 / visit"', false)
            ->assertSee('data-visit-price="Rp105.000 / visit"', false);
    }

    public function test_tarif_visit_tidak_ditimpa_kelas_trial(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Preschool', fee: 360000);
        $this->makeClass(Carbon::today()->addDays(2), category: 'Basic Mewarnai', fee: 120000, type: 'trial');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('data-regular-price="Rp360.000 / bulan"', false)
            ->assertDontSee('Rp120.000 / visit')
            ->assertSee('data-visit-price="Rp105.000 / visit"', false);
    }

    public function test_tombol_daftar_kartu_program_membawa_slug_programnya(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Mewarnai');

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee(e(route('public.contact', ['kelas' => 'coloring', 'tipe' => 'regular'])), false)
            ->assertSee(e(route('public.contact', ['kelas' => 'coloring', 'tipe' => 'visit'])), false);

        // …dan tautan itu memang mempra-pilih program & tipenya di form kontak.
        $this->get(route('public.contact', ['kelas' => 'coloring', 'tipe' => 'visit']))
            ->assertOk()
            ->assertSee('value="coloring" selected', false)
            ->assertSee('value="visit" selected', false);
    }

    public function test_tabel_jadwal_umum_menyebut_hari_dan_jam_dari_database(): void
    {
        // Dua slot berbeda hari pada jam yang sama digabung jadi satu kalimat.
        // Label jadwal statis di config (mis. Preschool) dilepas dulu supaya
        // yang diuji tetap rangkuman dari slot kelas.
        config(['site.programs' => collect(config('site.programs'))
            ->map(fn (array $program) => array_diff_key($program, ['schedule_label' => true]))
            ->all()]);
        $senin = Carbon::today()->next(Carbon::MONDAY);

        $this->makeClass($senin, category: 'Preschool', time: '15:00:00', endTime: '16:30:00');
        $this->makeClass($senin->copy()->addDays(3), category: 'Preschool', time: '15:00:00', endTime: '16:30:00');

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
            'class_type' => 'regular',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '0812 3456 7890',
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
            'class_type' => 'regular',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '0812 3456 7890',
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
        $this->assertStringContainsString('Coloring', $rendered);
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
            'class_type' => 'visit',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
            'program' => 'coloring',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'child_name' => 'Alya Putri',
            'class_type' => 'visit',
            'program' => 'coloring',
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
            'program' => 'Basic Mewarnai',
            'date_of_birth' => Carbon::tomorrow()->toDateString(),
        ])->assertSessionHasErrors(['class_type', 'program', 'date_of_birth']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_dropdown_program_berisi_tiga_program_reguler(): void
    {
        $this->makeClass(Carbon::today()->addDay(), category: 'Basic Mewarnai');

        // Kategori kelas di database tidak lagi jadi pilihan pendaftaran.
        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('value="preschool"', false)
            ->assertSee('value="sketching"', false)
            ->assertSee('value="coloring"', false)
            // Holiday Class lewat chat admin, bukan form.
            ->assertDontSee('value="holiday"', false)
            ->assertSee('value="regular"', false)
            ->assertSee('value="visit"', false)
            ->assertDontSee('value="Basic Mewarnai"', false);
    }

    public function test_kategori_kelas_database_ditolak_sebagai_program(): void
    {
        $class = $this->makeClass(Carbon::today()->addDay(), category: 'Basic Mewarnai');

        $this->post(route('public.contact.store'), [
            'child_name' => 'Alya Putri',
            'date_of_birth' => '2018-05-17',
            'class_type' => 'regular',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
            'program' => $class->class_category,
        ])->assertSessionHasErrors('program');
    }

    public function test_tautan_lama_berisi_kategori_kelas_dipetakan_ke_programnya(): void
    {
        // ?kelas=Basic Mewarnai (tautan lama) → program Coloring terpilih.
        $this->get(route('public.contact', ['kelas' => 'Basic Mewarnai']))
            ->assertOk()
            ->assertSee('value="coloring" selected', false);
    }

    public function test_form_kontak_menolak_data_tidak_lengkap(): void
    {
        // Semua isian wajib kecuali usia anak & pesan.
        $this->post(route('public.contact.store'), ['child_name' => 'Alya'])
            ->assertSessionHasErrors([
                'date_of_birth', 'class_type', 'program',
                'parent_name', 'parent_phone', 'address',
            ])
            ->assertSessionDoesntHaveErrors(['child_age', 'message']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_semua_program_di_form_bisa_reguler_atau_visit(): void
    {
        Mail::fake();
        // Enam kiriman beruntun menyentuh batas throttle form kontak.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $payload = [
            'child_name' => 'Alya Putri',
            'date_of_birth' => '2018-05-17',
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
        ];

        // Tidak ada satu pun kelas di database: semua kombinasi tetap diterima.
        foreach (['preschool', 'sketching', 'coloring'] as $program) {
            foreach (['regular', 'visit'] as $type) {
                $this->post(route('public.contact.store'), $payload + ['program' => $program, 'class_type' => $type])
                    ->assertSessionHasNoErrors();
            }
        }

        $this->assertDatabaseCount('leads', 6);
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
            'program' => 'sketching',
            'class_type' => 'visit',
        ]);

        $this->assertSame('Sketching', $lead->programName());
        $this->assertSame('Visit (sekali datang)', $lead->classTypeName());
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
