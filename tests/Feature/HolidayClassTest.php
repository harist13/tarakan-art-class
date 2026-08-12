<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\HolidayClass;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Modul Holiday Class: CRUD admin + sambungannya ke website publik.
 *
 * Inti yang dijaga di sini: sebelum ada modul ini, jadwal/kapasitas/biaya Holiday
 * Class hanya teks statis di config/site.php sehingga admin tidak bisa
 * mengumumkan sesi tanpa deploy. Tes memastikan sesi dari database benar-benar
 * menggantikan teks statis itu, dan kembali ke teks statis begitu sesinya lewat.
 */
class HolidayClassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tanggal dibekukan supaya "mendatang" vs "sudah lewat" tidak bergantung
        // pada kapan tes dijalankan.
        Carbon::setTestNow('2026-08-12 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::create([
            'full_name' => 'Admin Uji',
            'email' => 'admin@example.com',
            'username' => 'adminuji',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function holidaySession(array $overrides = []): HolidayClass
    {
        return HolidayClass::create($overrides + [
            'class_name' => 'Melukis Tote Bag',
            'schedule' => '2026-08-22 09:00:00',
            'capacity' => 20,
            'price' => 175000,
        ]);
    }

    public function test_modul_holiday_class_butuh_login(): void
    {
        $this->get(route('holiday-classes.index'))->assertRedirect(route('login'));
        $this->post(route('holiday-classes.store'), [])->assertRedirect(route('login'));
    }

    public function test_form_tambah_dan_edit_bisa_dibuka(): void
    {
        $session = $this->holidaySession();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('holiday-classes.create'))
            ->assertOk()
            ->assertSee('Jadwalkan Holiday Class');

        $this->actingAs($admin)->get(route('holiday-classes.edit', $session))
            ->assertOk()
            // Jadwal tersimpan harus terisi kembali dalam format datetime-local.
            ->assertSee('value="2026-08-22T09:00"', false);
    }

    public function test_admin_bisa_menjadwalkan_sesi(): void
    {
        $this->actingAs($this->admin())
            ->post(route('holiday-classes.store'), [
                'class_name' => 'Clay & Keramik Mini',
                'schedule' => '2026-08-22T09:00',
                'capacity' => 15,
                'price' => 200000,
            ])
            ->assertRedirect(route('holiday-classes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('holiday_classes', [
            'class_name' => 'Clay & Keramik Mini',
            'capacity' => 15,
        ]);

        $this->assertSame('2026-08-22 09:00:00', HolidayClass::first()->schedule->toDateTimeString());
    }

    public function test_kapasitas_minimal_satu_dan_jadwal_wajib(): void
    {
        $this->actingAs($this->admin())
            ->post(route('holiday-classes.store'), [
                'class_name' => 'Sesi Tanpa Jadwal',
                'schedule' => '',
                'capacity' => 0,
                'price' => 100000,
            ])
            ->assertSessionHasErrors(['schedule', 'capacity']);

        $this->assertDatabaseCount('holiday_classes', 0);
    }

    public function test_admin_bisa_mengubah_dan_menghapus_sesi(): void
    {
        $session = $this->holidaySession();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('holiday-classes.update', $session), [
                'class_name' => 'Mural Mini',
                'schedule' => '2026-08-29T13:30',
                'capacity' => 10,
                'price' => 250000,
            ])
            ->assertRedirect(route('holiday-classes.index'));

        $this->assertDatabaseHas('holiday_classes', ['id' => $session->id, 'class_name' => 'Mural Mini']);

        $this->actingAs($admin)
            ->delete(route('holiday-classes.destroy', $session))
            ->assertRedirect(route('holiday-classes.index'));

        $this->assertDatabaseCount('holiday_classes', 0);
    }

    public function test_daftar_sesi_menyaring_yang_sudah_lewat(): void
    {
        $this->holidaySession(['class_name' => 'Sesi Mendatang']);
        $this->holidaySession(['class_name' => 'Sesi Lampau', 'schedule' => '2026-07-04 09:00:00']);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('holiday-classes.index'))
            ->assertOk()
            ->assertSee('Sesi Mendatang')
            ->assertDontSee('Sesi Lampau');

        // Kartu ringkasan "Sesi Terdekat" tetap menyebut sesi mendatang di halaman
        // riwayat, jadi yang diperiksa di sini keberadaan sesi lampau + penandanya.
        $this->actingAs($admin)->get(route('holiday-classes.index', ['filter' => 'past']))
            ->assertOk()
            ->assertSee('Sesi Lampau')
            ->assertSee('Sudah lewat');
    }

    public function test_sesi_mendatang_mengisi_kartu_program_di_website(): void
    {
        $this->holidaySession();

        $this->get(route('public.programs'))
            ->assertOk()
            // Jadwal, kapasitas, & biaya diambil dari sesi di database…
            ->assertSee('22 Agu 2026')
            ->assertSee('09.00 WITA')
            ->assertSee('20 anak per sesi')
            ->assertSee('Rp175.000 / sesi')
            ->assertSee('Melukis Tote Bag')
            // …bukan lagi teks perkiraan di config.
            ->assertDontSee('Musiman — libur sekolah')
            ->assertDontSee('Rp150.000 / sesi');
    }

    public function test_website_kembali_ke_teks_config_bila_sesi_sudah_lewat(): void
    {
        $this->holidaySession(['schedule' => '2026-07-04 09:00:00']);

        $this->get(route('public.programs'))
            ->assertOk()
            ->assertSee('Musiman — libur sekolah')
            ->assertSee('Rp150.000 / sesi')
            ->assertDontSee('Melukis Tote Bag');
    }

    public function test_sesi_mendatang_tampil_di_pengumuman_halaman_jadwal(): void
    {
        $this->holidaySession();

        $this->get(route('public.schedule'))
            ->assertOk()
            ->assertSee('Melukis Tote Bag')
            ->assertSee('Kapasitas 20 anak', false)
            ->assertSee('22 Agu 2026')
            // Baris "Jadwal umum per program" tidak lagi menandai perkiraan.
            ->assertDontSee('Musiman — libur sekolah');
    }

    public function test_halaman_jadwal_tetap_normal_tanpa_sesi_holiday_class(): void
    {
        $this->get(route('public.schedule'))
            ->assertOk()
            ->assertSee('Musiman — libur sekolah')
            ->assertSee('Pengumuman');
    }

    public function test_sesi_tampil_di_kalender_jadwal_dengan_warna_sendiri(): void
    {
        $this->holidaySession();

        $response = $this->actingAs($this->admin())->get(route('schedules.calendar'));

        $response->assertOk()
            ->assertSee('Melukis Tote Bag')
            // Fuchsia, tidak dipakai jenis event lain di kalender.
            ->assertSee('#C026D3', false)
            ->assertSee('Holiday Class')
            // Klik event mengarah ke form edit sesinya, bukan replacement.
            ->assertSee('Kelola Holiday Class');

        // Legenda kalender ikut menjelaskan warnanya.
        $this->assertStringContainsString('background:#C026D3;', $response->getContent());

        // Sesi mendatang bukan riwayat, jadi tidak disembunyikan toggle
        // "Hanya slot available".
        $response->assertSee('"past":false', false);
    }

    public function test_sesi_liburan_lampau_ditandai_past_di_kalender(): void
    {
        $this->holidaySession(['schedule' => '2026-07-04 09:00:00']);

        $this->actingAs($this->admin())->get(route('schedules.calendar'))
            ->assertOk()
            ->assertSee('"past":true', false);
    }

    /**
     * Penjaga regresi untuk definisi "sudah lewat".
     *
     * Kalender wajib memakai HolidayClass::hasPassed() (batas awal hari), bukan
     * schedule->isPast() (batas jam persis). Kalau tertukar, sesi hari ini hilang
     * dari kalender begitu jam mulainya terlewat — padahal website masih
     * mengiklankannya dan sesinya justru sedang berlangsung.
     */
    public function test_sesi_liburan_hari_ini_tetap_tampil_walau_jam_mulainya_terlewat(): void
    {
        // Waktu dibekukan pukul 08.00; sesi ini mulai pukul 07.00 hari yang sama.
        $this->holidaySession(['schedule' => '2026-08-12 07:00:00']);

        $this->actingAs($this->admin())->get(route('schedules.calendar'))
            ->assertOk()
            ->assertSee('"past":false', false)
            ->assertDontSee('"past":true', false);
    }

    // ─── Form pendaftaran di website publik ────────────────────────────
    //
    // Dropdown "Kelas yang diminati" diisi dari tabel `classes`, jadi tes di
    // bawah selalu membuat satu kelas reguler dulu — tanpa itu form jatuh ke
    // daftar program di config dan bukan jalur yang dipakai di produksi.

    private function regularClass(): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak Ayu', 'status' => 'active']);

        return ClassRoom::create([
            'class_name' => 'Coloring Sore',
            'class_category' => 'coloring',
            'tutor_id' => $tutor->id,
            'capacity' => 8,
            'schedule_date' => '2026-08-15',
            'schedule_time' => '15:00:00',
            'class_fee' => 275000,
            'status' => 'open',
        ]);
    }

    public function test_holiday_class_jadi_pilihan_kelas_saat_ada_sesi(): void
    {
        $this->regularClass();
        $this->holidaySession();

        $this->get(route('public.contact'))
            ->assertOk()
            // Label menyebut tema & tanggal sesi, bukan cuma "Holiday Class".
            ->assertSee('Holiday Class — Melukis Tote Bag (22 Agu 2026)')
            ->assertSee('data-category="holiday"', false);
    }

    public function test_holiday_class_tidak_ditawarkan_saat_belum_ada_sesi(): void
    {
        $this->regularClass();

        // "Holiday Class" sendiri tetap muncul (jam operasional & dropdown tipe
        // kelas), jadi yang diperiksa adalah opsi kelasnya yang tidak ada.
        $this->get(route('public.contact'))
            ->assertOk()
            ->assertDontSee('data-category="holiday"', false);
    }

    public function test_tombol_daftar_kelas_ini_mempra_pilih_holiday_class(): void
    {
        $this->regularClass();
        $this->holidaySession();

        $response = $this->get(route('public.contact', ['kelas' => 'holiday']));

        $response->assertOk();
        $this->assertSame('holiday', $response->viewData('selected'));
        $this->assertSame('holiday', $response->viewData('selectedType'));
    }

    public function test_tipe_kelas_tetap_dipra_pilih_walau_sesi_belum_dijadwalkan(): void
    {
        $this->regularClass();

        $response = $this->get(route('public.contact', ['kelas' => 'holiday']));

        $response->assertOk();
        // Tidak ada sesi untuk dipilih, tapi niat orang tua tidak hilang: tipe
        // kelasnya tetap terisi sehingga form menampilkan petunjuk "belum ada jadwal".
        $this->assertNull($response->viewData('selected'));
        $this->assertSame('holiday', $response->viewData('selectedType'));
    }

    /**
     * @return array<string, mixed>
     */
    private function leadPayload(): array
    {
        return [
            'child_name' => 'Alya Putri',
            'date_of_birth' => '2018-05-17',
            'child_age' => 7,
            'parent_name' => 'Bu Rina',
            'parent_phone' => '081234567890',
            'parent_email' => 'rina@example.com',
            'address' => 'Jl. Mulawarman No. 3, Tarakan',
        ];
    }

    public function test_lead_peminat_holiday_class_tersimpan(): void
    {
        Mail::fake();

        $this->regularClass();
        $this->holidaySession();

        $this->post(route('public.contact.store'), $this->leadPayload() + [
            'class_type' => 'holiday',
            'program' => 'holiday',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'child_name' => 'Alya Putri',
            'class_type' => 'holiday',
            'program' => 'holiday',
        ]);
    }

    public function test_pilihan_kelas_wajib_hanya_bila_sesi_holiday_class_ada(): void
    {
        Mail::fake();

        $this->regularClass();

        // Belum ada sesi → form tidak boleh buntu, pilihan kelas dilonggarkan.
        $this->post(route('public.contact.store'), $this->leadPayload() + ['class_type' => 'holiday'])
            ->assertSessionHasNoErrors();

        $this->holidaySession();

        // Ada sesi → pilihan kelas wajib, sama seperti perilaku dropdown.
        $this->post(route('public.contact.store'), $this->leadPayload() + ['class_type' => 'holiday'])
            ->assertSessionHasErrors('program');
    }
}
