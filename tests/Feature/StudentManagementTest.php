<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: Menu "Murid & Wali" (Student Management / F2).
 * Menguji index+filter, create, store+validasi, edit, update, dan destroy (otorisasi).
 */
class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
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

    private function makeClass(string $category = 'drawing', int $capacity = 5, string $status = 'open'): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'full-time']);

        return ClassRoom::create([
            'class_category' => $category,
            'tutor_id' => $tutor->id,
            'capacity' => $capacity,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'class_fee' => 100000,
            'status' => $status,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => '081234567890',
            'instagram_username' => null,
            'address' => 'Jl. Mawar 1',
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => '2026-01-15',
        ], $overrides);
    }

    // ─── PENDAFTARAN KE SLOT TERPILIH ──────────────────────────────

    /**
     * Slot yang diklik di layar Ketersediaan Slot itulah yang terisi — bukan slot
     * lain sekategori yang kebetulan dipilihkan sistem.
     */
    public function test_murid_terdaftar_ke_jadwal_yang_dipilih(): void
    {
        // Slot pagi lebih dulu dibuat, jadi dialah yang akan ditebak sistem bila
        // jadwalnya tidak disebut — justru itu yang diuji tidak terjadi.
        $pagi = $this->makeClass('drawing');
        $sore = $this->makeClass('drawing');
        $sore->update(['schedule_time' => '15:00']);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload(['class_id' => $sore->id]))
            ->assertRedirect(route('students.index'))
            ->assertSessionHasNoErrors();

        $student = Student::where('name', 'Budi Santoso')->firstOrFail();

        $this->assertSame([$sore->id], $student->classes->pluck('id')->all());
        $this->assertSame(0, $pagi->students()->count());
    }

    /**
     * Tanpa jadwal yang disebut, perilaku lama dipertahankan: slot pertama di
     * kategori itu yang masih muat. Pendaftaran lewat jalur lain tidak boleh gagal
     * hanya karena tak menyertakan jadwal.
     */
    public function test_tanpa_jadwal_terpilih_sistem_memilihkan_slot_yang_muat(): void
    {
        $penuh = $this->makeClass('drawing', capacity: 1);
        $penuh->students()->attach(
            Student::create($this->validPayload(['name' => 'Anak Lama']))->id,
            ['status' => 'active', 'enrolled_at' => now()->toDateString()]
        );
        $kosong = $this->makeClass('drawing');

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload())
            ->assertRedirect(route('students.index'))
            ->assertSessionHasNoErrors();

        $student = Student::where('name', 'Budi Santoso')->firstOrFail();

        $this->assertSame([$kosong->id], $student->classes->pluck('id')->all());
    }

    public function test_jadwal_yang_penuh_ditolak(): void
    {
        $penuh = $this->makeClass('drawing', capacity: 1);
        $penuh->students()->attach(
            Student::create($this->validPayload(['name' => 'Anak Lama']))->id,
            ['status' => 'active', 'enrolled_at' => now()->toDateString()]
        );
        // Slot kedua yang masih kosong membuat kategorinya lolos validasi
        // class_type — jadi yang menolak di sini benar-benar cek per jadwal.
        $this->makeClass('drawing');

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload(['class_id' => $penuh->id]))
            ->assertSessionHasErrors('class_id');

        $this->assertSame(1, $penuh->students()->count());
    }

    /**
     * Slot penuh ditolak server, tapi itu jaring terakhir — di layar pun ia harus
     * sudah tidak bisa diklik.
     *
     * Penonaktifannya ditegaskan dua kali: atribut `disabled` sejak dari server,
     * dan `data-no-search` supaya dropdown ini tetap select asli. Tom Select
     * menyalin daftar opsi sekali saat inisialisasi dan tidak pernah membacanya
     * lagi, sehingga slot yang dinonaktifkan penyaring tetap bisa diklik di daftar
     * yang terlihat — persis jalur yang membuat kelas penuh sempat terpilih.
     */
    public function test_jadwal_penuh_tidak_bisa_dipilih_di_layar(): void
    {
        $penuh = $this->makeClass('drawing', capacity: 1);
        $penuh->students()->attach(
            Student::create($this->validPayload(['name' => 'Anak Lama']))->id,
            ['status' => 'active', 'enrolled_at' => now()->toDateString()]
        );
        $kosong = $this->makeClass('coloring');

        $html = $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<select name="class_id"[^>]*data-no-search/',
            $html,
            'Dropdown jadwal harus tetap select asli agar opsinya benar-benar bisa dinonaktifkan.'
        );

        // Slot penuh tetap terlihat sebagai keterangan, tapi mati.
        $this->assertMatchesRegularExpression(
            '/<option value="'.$penuh->id.'"[^>]*disabled/s',
            $html,
            'Slot penuh harus dinonaktifkan sejak dari server.'
        );

        // Slot yang masih muat tidak ikut terkena.
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.$kosong->id.'"[^>]*disabled/s',
            $html
        );
    }

    /**
     * Kategori diganti tapi jadwal lama tertinggal di form: yang tertinggal
     * diabaikan, bukan dipakai mendaftarkan anak ke kategori yang salah.
     */
    public function test_jadwal_beda_kategori_diabaikan(): void
    {
        $drawing = $this->makeClass('drawing');
        $coloring = $this->makeClass('coloring');

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload([
                'class_type' => 'coloring',
                'class_id' => $drawing->id,
            ]))
            ->assertRedirect(route('students.index'))
            ->assertSessionHasNoErrors();

        $student = Student::where('name', 'Budi Santoso')->firstOrFail();

        $this->assertSame([$coloring->id], $student->classes->pluck('id')->all());
    }

    public function test_form_tambah_murid_terisi_dari_slot_yang_diklik(): void
    {
        $this->makeClass('drawing');
        $coloring = $this->makeClass('coloring');

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create', ['class_id' => $coloring->id]))
            ->assertOk()
            ->assertSee('Jadwal kelas')
            ->assertSee('name="class_id"', false)
            // Kategori slot itu ikut terpilih, jadi tak perlu dipilih ulang.
            ->assertSee('value="coloring"', false);
    }

    // ─── INDEX + FILTER ────────────────────────────────────────────

    public function test_index_page_loads(): void
    {
        $this->makeClass();
        Student::create($this->validPayload(['name' => 'Ani Listing']));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Data murid & wali', false)
            ->assertSee('Ani Listing');
    }

    public function test_index_search_filters_by_name(): void
    {
        Student::create($this->validPayload(['name' => 'Zaki Unik']));
        Student::create($this->validPayload(['name' => 'Lain Orang']));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.index', ['search' => 'Zaki']))
            ->assertOk()
            ->assertSee('Zaki Unik')
            ->assertDontSee('Lain Orang');
    }

    public function test_index_filters_by_status(): void
    {
        Student::create($this->validPayload(['name' => 'Si Aktif', 'status' => 'active']));
        Student::create($this->validPayload(['name' => 'Si Nonaktif', 'status' => 'inactive']));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Si Nonaktif')
            ->assertDontSee('Si Aktif');
    }

    public function test_index_filters_by_class(): void
    {
        $classA = $this->makeClass('drawing');
        $classB = $this->makeClass('coloring');

        $inA = Student::create($this->validPayload(['name' => 'Murid Kelas A']));
        $inA->classes()->attach($classA->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);
        $inB = Student::create($this->validPayload(['name' => 'Murid Kelas B']));
        $inB->classes()->attach($classB->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.index', ['class_id' => $classA->id]))
            ->assertOk()
            ->assertSee('Murid Kelas A')
            ->assertDontSee('Murid Kelas B');
    }

    // ─── CREATE + STORE ────────────────────────────────────────────

    public function test_create_page_loads(): void
    {
        $this->makeClass();

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('Tambah murid baru');
    }

    public function test_store_creates_student_and_enrolls_class(): void
    {
        $class = $this->makeClass('drawing');
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)->post(route('students.store'), $this->validPayload([
            'class_type' => 'drawing',
        ]));

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        $student = Student::first();
        $this->assertNotNull($student);
        $this->assertSame('STD001', $student->student_id, 'ID murid harus auto-generate STD001');
        $this->assertTrue($student->classes->contains($class->id), 'Murid harus terdaftar ke kelas');
        $this->assertSame('active', $student->classes->first()->pivot->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'created']);
    }

    /**
     * Pekan mulai dicatat pada pendaftaran murid ke kelas, bukan pada muridnya:
     * satu kelas dimasuki anak-anak yang datang di pekan berbeda-beda.
     */
    public function test_store_menyimpan_pekan_mulai_di_pendaftaran_kelas(): void
    {
        $class = $this->makeClass('drawing');
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('students.store'), $this->validPayload([
            'start_week' => 3,
        ]))->assertRedirect(route('students.index'))->assertSessionHasNoErrors();

        $student = Student::with('classes')->first();

        $this->assertSame(3, (int) $student->classes->first()->pivot->start_week);
        $this->assertSame(3, $student->startWeek());
    }

    /**
     * Tanpa pilihan admin, pekan diturunkan dari PERTEMUAN PERTAMA kelasnya —
     * bukan dari tanggal murid didaftarkan.
     *
     * Yang dijawab harga bulan pertama adalah "anak ini dapat berapa pekan", dan
     * itu ditentukan kapan ia mulai masuk, bukan kapan orang tuanya mendaftar.
     */
    public function test_pekan_mulai_terisi_sendiri_dari_pertemuan_pertama(): void
    {
        $class = $this->makeClass('drawing');
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('students.store'), $this->validPayload())
            ->assertRedirect(route('students.index'))->assertSessionHasNoErrors();

        $student = Student::with('classes')->first();
        $harapan = \App\Models\ClassRoom::weekOfMonth($class->nextOccurrence());

        $this->assertSame($harapan, (int) $student->classes->first()->pivot->start_week);
    }

    /**
     * Bedanya nyata, bukan teoretis: didaftarkan di pekan pertama untuk kelas
     * yang sesinya baru jalan di pekan ketiga.
     *
     * Aturan lama membaca "pekan ke-1" dari tanggal pendaftaran lalu menagih
     * sebulan penuh, padahal anaknya cuma kebagian dua pertemuan.
     */
    public function test_pekan_mulai_mengabaikan_tanggal_pendaftaran_yang_beda_pekan(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-01 08:00'));

        $class = $this->makeClass('drawing');
        // Sesi tunggal di pekan ketiga — 16 September.
        $class->update([
            'class_type' => 'trial',
            'is_recurring' => false,
            'schedule_date' => '2026-09-16',
        ]);

        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('students.store'), $this->validPayload())
            ->assertRedirect(route('students.index'))->assertSessionHasNoErrors();

        $student = Student::with('classes')->first();

        // Didaftarkan 1 September (pekan ke-1), tapi pertemuan pertamanya
        // 16 September (pekan ke-3) — yang berlaku pertemuannya.
        $this->assertSame(1, \App\Models\ClassRoom::weekOfMonth(now()));
        $this->assertSame(3, (int) $student->classes->first()->pivot->start_week);
    }

    /**
     * Sambungan ujung-ke-ujung: dari kiriman form sampai nominal invoicenya.
     *
     * Tiap potong rantainya sudah punya tesnya sendiri — form → pivot di sini,
     * pivot → nominal di BillingPeriodTest — tapi tanpa tes ini keduanya bisa
     * tetap hijau sementara sambungannya putus, dan yang keliru adalah angka
     * rupiah yang ditagihkan ke orang tua.
     */
    public function test_nominal_invoice_pertama_mengikuti_pertemuan_pertama(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-01 08:00'));

        $class = $this->makeClass('drawing');
        $class->update([
            'class_type' => 'trial',
            'is_recurring' => false,
            // Pertemuan pertamanya pekan ke-3, sedangkan muridnya didaftarkan
            // di pekan ke-1.
            'schedule_date' => '2026-09-16',
            'class_fee' => 450000,
            'registration_fee' => 50000,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload())
            ->assertRedirect(route('students.index'))
            ->assertSessionHasNoErrors();

        $student = Student::with('classes')->first();

        // Masuk pekan ke-3 → kebagian 2 dari 4 pekan → 2 × (450.000 / 4).
        $this->assertEqualsWithDelta(225000.0, $student->firstMonthFee(), 0.01);

        // Uang pendaftaran menumpang di atasnya, hanya untuk invoice pertama.
        $this->assertEqualsWithDelta(275000.0, $student->invoiceAmount(), 0.01);

        // Kalau pekannya dibaca dari tanggal pendaftaran (1 Sep = pekan ke-1),
        // angkanya akan jadi 450.000 + 50.000. Ini yang dijaga tes ini.
        $this->assertNotEqualsWithDelta(500000.0, $student->invoiceAmount(), 0.01);
    }

    /** Nominal & tanggal pertemuan pertama ikut dikirim ke form, bukan dihitung ulang di layar. */
    public function test_form_membawa_biaya_dan_sesi_pertama_tiap_jadwal(): void
    {
        $class = $this->makeClass('drawing');
        $class->update(['class_fee' => 450000, 'registration_fee' => 50000]);

        $html = $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-fee="450000"', $html);
        $this->assertStringContainsString('data-registration="50000"', $html);
        $this->assertStringContainsString('data-session-date="'.$class->nextOccurrence()->toDateString().'"', $html);
    }

    /**
     * Satu kategori bisa memuat slot Reguler dan Trial sekaligus, dan tarifnya
     * berbeda. Tanpa tipe di label jadwalnya, keduanya terbaca sebagai baris yang
     * sama — dan salah pilih baru ketahuan di nominal invoice.
     */
    public function test_label_jadwal_menyebut_tipe_kelasnya(): void
    {
        $reguler = $this->makeClass('drawing');
        $trial = $this->makeClass('drawing');
        $trial->update([
            'class_type' => 'trial',
            'is_recurring' => false,
            'schedule_date' => now()->addWeek()->toDateString(),
        ]);

        $html = $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))->assertOk()->getContent();

        $this->assertStringContainsString('· Reguler (', $html);
        $this->assertStringContainsString('· Trial Class (', $html);

        // Kode kelas tidak ikut di label: admin tidak sedang mencocokkan data
        // dengan menu lain di form ini.
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.$reguler->id.'"[^>]*>[^<]*'.preg_quote($reguler->class_code, '/').'/s',
            $html
        );
    }

    /**
     * Jadwal punya dua tampilan yang bergantian: kotak baca-saja saat kategorinya
     * hanya punya satu slot, dropdown saat ada lebih.
     *
     * Yang bisa diuji di sini keberadaan keduanya — pergantiannya dikerjakan
     * JavaScript, di luar jangkauan PHPUnit.
     */
    public function test_form_menyediakan_kotak_baca_saja_dan_dropdown_jadwal(): void
    {
        $this->makeClass('drawing');

        $html = $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))->assertOk()->getContent();

        $this->assertStringContainsString('id="class_id_readonly"', $html);
        $this->assertStringContainsString('id="class_id_control"', $html);
        // Kotak baca-saja tidak boleh punya name — kalau ikut terkirim, isinya
        // yang berupa teks jadwal akan menimpa id kelas yang sebenarnya.
        $this->assertStringContainsString('id="class_id_display"', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="class_id_display"[^>]*name=/', $html);
    }

    /**
     * Pekan mulai adalah kotak isian tersendiri, dengan keterangan yang menyebut
     * akibatnya: pekan ke berapa menentukan berapa pekan yang ditagih di bulan
     * pertama, dan itu yang perlu dilihat admin — bukan angkanya diulang.
     */
    public function test_form_murid_menampilkan_dropdown_pekan_mulai(): void
    {
        $this->makeClass('drawing');

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('Mulai minggu ke-')
            ->assertSee('name="start_week"', false)
            ->assertSee('Minggu ke-1')
            ->assertSee('Minggu ke-'.\App\Models\ClassRoom::WEEKS_PER_MONTH)
            ->assertSee('pekan di bulan pertama');
    }

    public function test_student_id_auto_increments(): void
    {
        $this->makeClass('drawing');
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('students.store'), $this->validPayload(['name' => 'Pertama']));
        $this->actingAs($admin)->post(route('students.store'), $this->validPayload(['name' => 'Kedua']));

        $this->assertSame(['STD001', 'STD002'], Student::orderBy('id')->pluck('student_id')->all());
    }

    public function test_store_requires_mandatory_fields(): void
    {
        $this->makeClass();

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload(['name' => '', 'class_type' => '']))
            ->assertSessionHasErrors(['name', 'class_type']);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_store_rejects_non_numeric_phone(): void
    {
        $this->makeClass();

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload(['phone_number' => '0812-abc']))
            ->assertSessionHasErrors('phone_number');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_store_rejects_full_or_closed_class_type(): void
    {
        $this->makeClass('drawing', capacity: 0, status: 'closed');

        $this->actingAs($this->makeUser('admin'))
            ->post(route('students.store'), $this->validPayload(['class_type' => 'drawing']))
            ->assertSessionHasErrors('class_type');
    }

    /**
     * Trial class yang tanggalnya sudah lewat tidak punya sesi mendatang, jadi
     * kategorinya tidak bisa diisi murid baru — walau kursinya masih kosong dan
     * kelasnya tidak ditutup.
     *
     * Dulu hanya "semua ditutup" & "semua penuh" yang menghalangi, sehingga
     * kategori seperti ini ditawarkan sebagai "Tersedia (1 kursi)" lalu mentok di
     * dropdown jadwal yang seluruh pilihannya mati.
     */
    public function test_kategori_yang_slotnya_sudah_lewat_tidak_bisa_dipilih(): void
    {
        $lewat = $this->makeClass('trial ra');
        $lewat->update([
            'class_type' => 'trial',
            'is_recurring' => false,
            'schedule_date' => now()->subWeek()->toDateString(),
        ]);
        $lewat->refresh();

        $this->assertSame('Sudah lewat', $lewat->availability()['text'], 'Prasyarat: slotnya memang sudah lewat.');
        $this->assertGreaterThan(0, $lewat->remainingSeats(), 'Prasyarat: kursinya justru masih kosong.');

        $admin = $this->makeUser('admin');

        // Di layar: tidak ditawarkan sebagai tersedia, dan tidak bisa dipilih.
        $html = $this->actingAs($admin)->get(route('students.create'))->assertOk()->getContent();

        $this->assertStringContainsString('trial ra — Sudah lewat', $html);
        $this->assertStringNotContainsString('trial ra — Tersedia', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="trial ra"[^>]*disabled/s',
            $html,
            'Kategori tanpa slot yang bisa diisi harus mati, sama seperti kelas penuh.'
        );

        // Di server: ditolak, dengan alasan yang benar — bukan "penuh".
        $this->actingAs($admin)
            ->post(route('students.store'), $this->validPayload(['class_type' => 'trial ra']))
            ->assertSessionHasErrors(['class_type' => 'Kelas untuk kategori trial ra tidak bisa dipilih: Sudah lewat.']);
    }

    /** Kursi yang dihitung hanya milik slot yang benar-benar bisa diisi. */
    public function test_kursi_tersedia_tidak_menghitung_slot_yang_sudah_lewat(): void
    {
        $aktif = $this->makeClass('drawing', capacity: 3);
        $lewat = $this->makeClass('drawing', capacity: 9);
        $lewat->update([
            'class_type' => 'trial',
            'is_recurring' => false,
            'schedule_date' => now()->subWeek()->toDateString(),
        ]);

        $html = $this->actingAs($this->makeUser('admin'))
            ->get(route('students.create'))->assertOk()->getContent();

        // 3 kursi dari slot yang aktif saja — bukan 12.
        $this->assertStringContainsString('drawing — Tersedia (3 kursi)', $html);
        $this->assertSame(3, $aktif->remainingSeats());
    }

    // ─── EDIT + UPDATE ─────────────────────────────────────────────

    public function test_edit_page_loads(): void
    {
        $this->makeClass();
        $student = Student::create($this->validPayload(['name' => 'Untuk Edit']));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('Untuk Edit');
    }

    public function test_update_modifies_student(): void
    {
        $class = $this->makeClass('drawing');
        $student = Student::create($this->validPayload(['name' => 'Nama Lama']));

        $response = $this->actingAs($this->makeUser('admin'))->put(route('students.update', $student), $this->validPayload([
            'name' => 'Nama Baru',
            'status' => 'inactive',
        ]));

        $response->assertRedirect(route('students.index'));
        $student->refresh();
        $this->assertSame('Nama Baru', $student->name);
        $this->assertSame('inactive', $student->status);
        $this->assertTrue($student->classes->contains($class->id));
        $this->assertDatabaseHas('activity_logs', ['action' => 'updated']);
    }

    // ─── DESTROY (OTORISASI) ───────────────────────────────────────

    public function test_super_admin_can_delete_student(): void
    {
        $student = Student::create($this->validPayload());

        $this->actingAs($this->makeUser('super_admin'))
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deleted']);
    }

    public function test_non_super_admin_cannot_delete_student(): void
    {
        $student = Student::create($this->validPayload());

        $this->actingAs($this->makeUser('admin'))
            ->delete(route('students.destroy', $student))
            ->assertForbidden();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('students.index'))->assertRedirect(route('login'));
    }
}
