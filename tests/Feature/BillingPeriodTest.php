<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: periode tagihan & penerbitan tagihan bulanan.
 *
 * Dua hal yang dijaga di sini:
 *
 *   1. Satu murid hanya boleh punya satu invoice per periode. Tagihan lepas
 *      (periode kosong) dikecualikan — biaya pendaftaran & pembelian alat
 *      memang boleh berulang dalam bulan yang sama.
 *   2. Penerbitan massal melewati murid yang sudah tertagih, bukan menimpanya,
 *      dan alasan tiap murid yang dilewati terlihat di pratinjau.
 */
class BillingPeriodTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        if ($this->admin) {
            return $this->admin;
        }

        Role::firstOrCreate(['name' => 'admin']);
        $user = User::create([
            'full_name' => 'Admin User',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $this->admin = $user;
    }

    private function makeClass(int $fee = 150000): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'active']);

        return ClassRoom::create([
            'class_category' => 'drawing',
            'tutor_id' => $tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->addDay()->toDateString(),
            'schedule_time' => '09:00',
            'class_fee' => $fee,
            'status' => 'open',
        ]);
    }

    private function makeStudent(string $name, ?ClassRoom $class = null): Student
    {
        $student = Student::create([
            'name' => $name,
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => '081234567890',
            'address' => 'Jl. Mawar 1',
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => '2026-01-15',
        ]);

        if ($class) {
            $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);
        }

        return $student;
    }

    /** Bentuk satu baris payments — untuk Payment::create() & form Edit. */
    private function invoicePayload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $student->id,
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'billing_period' => '2026-08',
            'payment_amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ], $overrides);
    }

    /**
     * Bentuk kiriman halaman Buat Invoice — selalu sekumpulan murid, baik satu
     * orang maupun sebulan penuh. Satu jalur, bukan dua seperti dulu.
     *
     * @param  array<int, Student>  $students
     */
    private function formPayload(array $students, array $overrides = []): array
    {
        return array_merge([
            'billing_period' => '2026-08',
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'students' => array_map(fn (Student $s) => $s->id, $students),
            'amounts' => collect($students)->mapWithKeys(fn (Student $s) => [$s->id => 150000])->all(),
        ], $overrides);
    }

    // ─── ATURAN SATU INVOICE PER PERIODE ───────────────────────────

    public function test_second_invoice_for_same_student_and_period_is_skipped(): void
    {
        $student = $this->makeStudent('Murid A', $this->makeClass());

        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$student]))
            ->assertRedirect(route('payments.index'));

        // Pratinjau sudah mengeluarkan murid ini dari daftar, jadi satu-satunya
        // cara sampai ke sini adalah halaman basi. Jawabannya melewati dengan
        // tenang, bukan error — tidak ada yang salah dengan kiriman admin.
        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$student]))
            ->assertSessionHas('error');

        $this->assertSame(1, Payment::where('student_id', $student->id)->count());
    }

    public function test_invoice_for_next_period_is_allowed(): void
    {
        $student = $this->makeStudent('Murid A', $this->makeClass());

        $this->actingAs($this->admin())->post(route('payments.store'), $this->formPayload([$student]));
        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$student], [
                'billing_period' => '2026-09',
                'payment_date' => '2026-09-01',
                'due_date' => '2026-09-08',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Payment::where('student_id', $student->id)->count());
    }

    public function test_editing_an_invoice_keeps_its_own_period(): void
    {
        $student = $this->makeStudent('Murid A', $this->makeClass());
        $payment = Payment::create($this->invoicePayload($student));

        // Menyimpan ulang invoice yang sama tidak boleh bentrok dengan dirinya sendiri.
        $this->actingAs($this->admin())
            ->put(route('payments.update', $payment), $this->invoicePayload($student, ['payment_amount' => 175000]))
            ->assertSessionHasNoErrors();

        $this->assertSame('175000.00', $payment->fresh()->payment_amount);
        $this->assertSame('2026-08', $payment->fresh()->billing_period);
    }

    public function test_editing_an_invoice_onto_an_occupied_period_is_rejected(): void
    {
        $student = $this->makeStudent('Murid A', $this->makeClass());
        Payment::create($this->invoicePayload($student));
        $september = Payment::create($this->invoicePayload($student, ['billing_period' => '2026-09']));

        // Di halaman Edit penolakannya tetap error yang menyebut invoice mana
        // yang sudah ada — di sini admin memang sedang salah, bukan kebetulan
        // mengirim ulang daftar yang basi.
        $this->actingAs($this->admin())
            ->put(route('payments.update', $september), $this->invoicePayload($student, ['billing_period' => '2026-08']))
            ->assertSessionHasErrors('billing_period');

        $this->assertStringContainsString(
            Payment::orderBy('id')->first()->invoice_number,
            session('errors')->first('billing_period')
        );
        $this->assertSame('2026-09', $september->fresh()->billing_period);
    }

    // ─── PRATINJAU & PENERBITAN ────────────────────────────────────

    public function test_preview_separates_billable_from_skipped(): void
    {
        $class = $this->makeClass();
        $layak = $this->makeStudent('Murid Layak', $class);
        $sudahDitagih = $this->makeStudent('Murid Sudah Ditagih', $class);
        $this->makeStudent('Murid Tanpa Kelas');

        Payment::create($this->invoicePayload($sudahDitagih));

        $response = $this->actingAs($this->admin())
            ->get(route('payments.create', ['period' => '2026-08']));

        $response->assertOk()
            ->assertSee('Agustus 2026')
            ->assertSee($layak->name)
            ->assertSee('sudah ditagih lewat')
            ->assertSee('belum terdaftar di kelas berbiaya');

        $billable = $response->viewData('billable');

        $this->assertSame([$layak->name], array_map(fn ($row) => $row['student']->name, $billable));
        $this->assertSame([150000.0], array_map(fn ($row) => $row['amount'], $billable));
        $this->assertTrue($response->viewData('preselect'), 'Mode periode: murid yang layak dicentang lebih dulu');
    }

    public function test_skip_reason_carries_a_tone_so_blade_never_guesses_from_text(): void
    {
        $class = $this->makeClass();
        $lunas = $this->makeStudent('Murid Lunas', $class);
        $belum = $this->makeStudent('Murid Belum Bayar', $class);
        $tanpaKelas = $this->makeStudent('Murid Tanpa Kelas');

        Payment::create($this->invoicePayload($lunas, ['payment_status' => 'paid']));
        Payment::create($this->invoicePayload($belum));

        $this->assertSame('paid', $lunas->fresh()->billingSkip('2026-08')['tone']);
        $this->assertSame('unpaid', $belum->fresh()->billingSkip('2026-08')['tone']);
        $this->assertSame('neutral', $tanpaKelas->fresh()->billingSkip('2026-08')['tone']);

        // Nadanya benar-benar sampai ke layar sebagai warna: hijau untuk yang
        // sudah lunas, merah untuk yang sudah ditagih tapi belum dibayar.
        $html = $this->actingAs($this->admin())
            ->get(route('payments.create', ['period' => '2026-08']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/text-success[^"]*">\s*sudah ditagih lewat \w+ \(Lunas\)/', $html);
        $this->assertMatchesRegularExpression('/text-danger[^"]*">\s*sudah ditagih lewat \w+ \(Unpaid\)/', $html);
        $this->assertMatchesRegularExpression('/text-muted[^"]*">\s*belum terdaftar di kelas berbiaya/', $html);
    }

    /**
     * Daftar panjang dilipat, BUKAN dipaginasi. Bedanya menentukan: baris yang
     * terlipat masih ada di dalam form, jadi centangnya tetap terkirim. Kalau
     * suatu hari ini diganti paginasi server, tes ini merah — dan memang harus,
     * sebab pindah halaman akan diam-diam membuang pilihan di halaman sebelumnya.
     */
    public function test_long_list_is_folded_but_every_row_still_submits(): void
    {
        $class = $this->makeClass();

        for ($i = 1; $i <= 28; $i++) {
            $this->makeStudent(sprintf('Murid %02d', $i), $class);
        }

        $html = $this->actingAs($this->admin())
            ->get(route('payments.create', ['period' => '2026-08']))
            ->assertOk()
            ->assertSee('Tampilkan 3 murid lainnya')
            ->getContent();

        // Ke-28 murid hadir sebagai checkbox; 3 di antaranya sekadar terlipat.
        $this->assertSame(28, substr_count($html, 'class="form-check-input pick"'));
        $this->assertSame(3, preg_match_all('/<tr[^>]*\bd-none\b/', $html), 'Hanya baris di atas batas yang terlipat');

        $ids = Student::pluck('id')->all();

        $this->actingAs($this->admin())
            ->post(route('payments.store'), [
                'billing_period' => '2026-08',
                'payment_date' => '2026-08-01',
                'due_date' => '2026-08-08',
                'payment_method' => 'qris',
                'payment_status' => 'unpaid',
                'students' => $ids,
                'amounts' => array_fill_keys($ids, 150000),
            ])
            ->assertRedirect(route('payments.index'));

        $this->assertSame(28, Payment::count(), 'Murid yang terlipat tetap ikut diterbitkan invoicenya');
    }

    public function test_skipped_list_is_searchable(): void
    {
        $class = $this->makeClass();
        $this->makeStudent('Murid Layak', $class);
        $sudah = $this->makeStudent('Murid Sudah', $class);
        Payment::create($this->invoicePayload($sudah));

        $html = $this->actingAs($this->admin())
            ->get(route('payments.create', ['period' => '2026-08']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="skipSearch"', $html);
        // Nomor invoice & alasan ikut bisa dicari, bukan cuma nama muridnya.
        $this->assertStringContainsString(
            Str::lower($sudah->name.' '.$sudah->student_id.' sudah ditagih lewat '.Payment::first()->invoice_number),
            $html
        );
    }

    public function test_store_issues_invoices_and_skips_the_already_billed(): void
    {
        $class = $this->makeClass();
        $satu = $this->makeStudent('Murid Satu', $class);
        $dua = $this->makeStudent('Murid Dua', $class);

        Payment::create($this->invoicePayload($dua));

        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$satu, $dua], ['payment_method' => 'qris']))
            ->assertRedirect(route('payments.index'));

        $this->assertSame(1, Payment::where('student_id', $satu->id)->count());
        $this->assertSame(1, Payment::where('student_id', $dua->id)->count(), 'Murid yang sudah ditagih tidak boleh dobel');

        $baru = Payment::where('student_id', $satu->id)->first();
        $this->assertSame('unpaid', $baru->payment_status);
        $this->assertSame('2026-08', $baru->billing_period);
        $this->assertNull($baru->transaction);
    }

    public function test_store_rejects_a_selected_student_without_amount(): void
    {
        $satu = $this->makeStudent('Murid Satu', $this->makeClass());

        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$satu], ['amounts' => [$satu->id => '']]))
            ->assertSessionHasErrors('amounts');

        $this->assertSame(0, Payment::count(), 'Nominal kosong tidak boleh jadi invoice Rp 0');
    }

    // ─── SATU MURID LEWAT HALAMAN YANG SAMA ────────────────────────

    public function test_one_student_is_just_one_checkbox(): void
    {
        $class = $this->makeClass();
        $satu = $this->makeStudent('Murid Satu', $class);
        $this->makeStudent('Murid Dua', $class);

        // Halaman menawarkan dua murid; yang dikirim hanya satu.
        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$satu]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::count());
        $this->assertSame($satu->id, Payment::first()->student_id);
    }

    public function test_paid_status_records_income_immediately(): void
    {
        $satu = $this->makeStudent('Murid Tunai', $this->makeClass());

        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$satu], ['payment_status' => 'paid']))
            ->assertSessionHasNoErrors();

        $payment = Payment::first();

        $this->assertSame('paid', $payment->payment_status);
        $this->assertNotNull($payment->transaction, 'Invoice Paid wajib punya pemasukan di Laporan Keuangan');
        $this->assertSame('150000.00', $payment->transaction->amount);
    }

    // ─── TAGIHAN LEPAS (TANPA PERIODE) ─────────────────────────────

    public function test_loose_invoices_may_repeat_in_the_same_month(): void
    {
        $student = $this->makeStudent('Murid A', $this->makeClass());

        foreach ([1, 2] as $ignored) {
            $this->actingAs($this->admin())
                ->post(route('payments.store'), $this->formPayload([$student], ['billing_period' => null]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Payment::where('student_id', $student->id)->count());
        $this->assertSame([null, null], Payment::pluck('billing_period')->all());
    }

    public function test_loose_invoice_does_not_block_the_monthly_one(): void
    {
        $student = $this->makeStudent('Murid Baru', $this->makeClass());

        // Biaya pendaftaran lebih dulu, lalu SPP bulan yang sama. Dengan periode
        // wajib, invoice kedua akan tertolak unique index dan admin buntu.
        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$student], ['billing_period' => null]));
        $this->actingAs($this->admin())
            ->post(route('payments.store'), $this->formPayload([$student]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Payment::count());
        $this->assertSame([null, '2026-08'], Payment::orderBy('id')->pluck('billing_period')->all());
    }

    public function test_loose_mode_shows_every_active_student_unchecked(): void
    {
        $class = $this->makeClass();
        $this->makeStudent('Murid Berkelas', $class);
        $tanpaKelas = $this->makeStudent('Murid Tanpa Kelas');
        $ditangguhkan = $this->makeStudent('Murid Ditangguhkan', $class);
        $ditangguhkan->suspend('menunggak');

        $response = $this->actingAs($this->admin())
            ->get(route('payments.create', ['lepas' => 1]))
            ->assertOk();

        // Murid tanpa kelas berbiaya justru kasus utamanya (biaya pendaftaran),
        // jadi ia wajib ikut tampil — beda dari mode periode.
        $names = array_map(fn ($row) => $row['student']->name, $response->viewData('billable'));

        $this->assertContains($tanpaKelas->name, $names);
        $this->assertContains($ditangguhkan->name, $names);
        $this->assertCount(3, $names);
        $this->assertFalse($response->viewData('preselect'), 'Tagihan lepas tidak pernah untuk semua murid');
        $this->assertEmpty($response->viewData('skipped'));
    }

    public function test_old_bulk_url_still_lands_on_the_merged_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/payments/bulk?period=2026-09')
            ->assertRedirect(route('payments.create', ['period' => '2026-09']));
    }

    public function test_suspended_student_is_not_billed_again(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent('Murid Ditangguhkan', $class);
        $student->suspend('menunggak');

        $response = $this->actingAs($this->admin())
            ->get(route('payments.create', ['period' => '2026-08']));

        $response->assertOk()->assertSee('ditangguhkan karena tunggakan');
        $this->assertCount(0, $response->viewData('billable'));
    }

    // ─── BADGE "BELUM DITAGIH" DI DAFTAR MURID ─────────────────────

    /** Invoice periode berjalan, supaya badge diuji terhadap bulan yang sama dengan aplikasi. */
    private function invoiceForThisPeriod(Student $student, string $status = 'unpaid'): Payment
    {
        return Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'billing_period' => Payment::periodFor(),
            'payment_amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => $status,
        ]);
    }

    public function test_student_without_invoice_this_period_is_flagged(): void
    {
        $student = $this->makeStudent('Murid Terlewat', $this->makeClass());

        $this->assertTrue($student->isUnbilledThisPeriod());
        $this->assertSame(
            'Belum ditagih '.Payment::labelForPeriod(Payment::periodFor()),
            $student->paymentBadgeLabel()
        );

        $this->actingAs($this->admin())
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Belum ditagih '.Payment::labelForPeriod(Payment::periodFor()));
    }

    public function test_badge_disappears_once_the_invoice_exists(): void
    {
        $student = $this->makeStudent('Murid Sudah Ditagih', $this->makeClass());
        $this->invoiceForThisPeriod($student);

        $student = $student->fresh();

        $this->assertFalse($student->isUnbilledThisPeriod(), 'Invoice Unpaid pun sudah menghapus badge — yang ditanya "sudah ditagih?", bukan "sudah dibayar?"');
        $this->assertSame('Belum bayar tagihan', $student->paymentBadgeLabel());
    }

    public function test_arrears_badge_takes_precedence(): void
    {
        $student = $this->makeStudent('Murid Nunggak', $this->makeClass());

        // Tunggakan bulan lalu; periode berjalan sengaja dibiarkan belum ditagih.
        Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(33)->toDateString(),
            'billing_period' => Payment::periodFor(now()->subMonthNoOverflow()),
            'payment_amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $student = $student->fresh();

        $this->assertTrue($student->isUnbilledThisPeriod());
        $this->assertStringStartsWith('Menunggak', (string) $student->paymentBadgeLabel(),
            'Tunggakan lebih mendesak daripada tagihan yang belum terbit');
    }

    public function test_students_that_are_deliberately_not_billed_carry_no_badge(): void
    {
        $tanpaKelas = $this->makeStudent('Murid Tanpa Kelas');
        $this->invoiceForThisPeriod($tanpaKelas, 'paid');

        $ditangguhkan = $this->makeStudent('Murid Ditangguhkan', $this->makeClass());
        $ditangguhkan->suspend('menunggak');

        $nonaktif = $this->makeStudent('Murid Nonaktif', $this->makeClass());
        $nonaktif->update(['status' => 'inactive']);

        foreach ([$tanpaKelas, $ditangguhkan, $nonaktif] as $student) {
            $this->assertFalse($student->fresh()->isUnbilledThisPeriod(),
                "{$student->name} memang tidak ditagih — tidak boleh ditandai terlewat");
        }
    }

    public function test_badge_and_bulk_preview_agree(): void
    {
        $class = $this->makeClass();
        $layak = $this->makeStudent('Murid Layak', $class);
        $sudah = $this->makeStudent('Murid Sudah', $class);
        $ditangguhkan = $this->makeStudent('Murid Ditangguhkan', $class);
        $this->makeStudent('Murid Tanpa Kelas');

        $this->invoiceForThisPeriod($sudah);
        $ditangguhkan->suspend('menunggak');

        $response = $this->actingAs($this->admin())
            ->get(route('students.index'))
            ->assertOk();

        $ditandai = $response->viewData('students')->getCollection()
            ->filter->isUnbilledThisPeriod()
            ->pluck('name')->sort()->values()->all();

        $preview = $this->actingAs($this->admin())
            ->get(route('payments.create', ['period' => Payment::periodFor()]))
            ->viewData('billable');

        $ditagih = collect($preview)->map(fn ($row) => $row['student']->name)->sort()->values()->all();

        $this->assertSame([$layak->name], $ditandai);
        $this->assertSame($ditandai, $ditagih,
            'Badge dan pratinjau Tagihan Bulanan harus menyebut murid yang sama persis');
    }

    // ─── SARINGAN "BELUM DITAGIH" DI DAFTAR MURID ──────────────────

    public function test_unbilled_filter_narrows_the_list(): void
    {
        $class = $this->makeClass();
        $layak = $this->makeStudent('Murid Layak', $class);
        $sudah = $this->makeStudent('Murid Sudah', $class);
        $this->invoiceForThisPeriod($sudah);

        $response = $this->actingAs($this->admin())
            ->get(route('students.index', ['unbilled' => 1]))
            ->assertOk();

        $this->assertSame(
            [$layak->name],
            $response->viewData('students')->getCollection()->pluck('name')->all()
        );
    }

    public function test_unbilled_count_ignores_the_other_filters(): void
    {
        $class = $this->makeClass();
        foreach (['Murid Satu', 'Murid Dua', 'Murid Tiga'] as $name) {
            $this->makeStudent($name, $class);
        }

        // Pencarian menyempitkan daftarnya, tapi angka pada tombol harus tetap
        // menjawab "berapa murid yang belum ditagih bulan ini" secara utuh.
        $response = $this->actingAs($this->admin())
            ->get(route('students.index', ['search' => 'Murid Satu']))
            ->assertOk();

        $this->assertCount(1, $response->viewData('students'));
        $this->assertSame(3, $response->viewData('unbilledCount'));
    }

    public function test_empty_result_under_the_filter_reads_as_good_news(): void
    {
        $student = $this->makeStudent('Murid Sudah', $this->makeClass());
        $this->invoiceForThisPeriod($student);

        $this->actingAs($this->admin())
            ->get(route('students.index', ['unbilled' => 1]))
            ->assertOk()
            ->assertSee('Semua murid sudah punya invoice untuk')
            ->assertDontSee('Tidak ada data murid.');
    }

    public function test_export_respects_the_unbilled_filter(): void
    {
        $class = $this->makeClass();
        $layak = $this->makeStudent('Murid Layak', $class);
        $sudah = $this->makeStudent('Murid Sudah', $class);
        $this->invoiceForThisPeriod($sudah);

        $csv = $this->actingAs($this->admin())
            ->get(route('export.students', ['unbilled' => 1, 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($layak->name, $csv);
        $this->assertStringNotContainsString($sudah->name, $csv);
    }

    /**
     * Penjaga penyimpangan: scope SQL dan billingSkipReason() adalah dua bentuk
     * dari aturan yang sama, dan keduanya harus selalu menjawab identik.
     */
    public function test_query_scope_matches_the_php_rule_for_every_case(): void
    {
        $berbayar = $this->makeClass(150000);
        $gratis = $this->makeClass(0);

        $this->makeStudent('Layak Ditagih', $berbayar);

        $sudahLunas = $this->makeStudent('Sudah Lunas', $berbayar);
        $this->invoiceForThisPeriod($sudahLunas, 'paid');

        $sudahUnpaid = $this->makeStudent('Sudah Unpaid', $berbayar);
        $this->invoiceForThisPeriod($sudahUnpaid);

        $ditangguhkan = $this->makeStudent('Ditangguhkan', $berbayar);
        $ditangguhkan->suspend('menunggak');

        $nonaktif = $this->makeStudent('Nonaktif', $berbayar);
        $nonaktif->update(['status' => 'inactive']);

        $this->makeStudent('Tanpa Kelas');
        $this->makeStudent('Kelas Gratis', $gratis);

        // Invoice periode LAIN tidak boleh dianggap menutup periode berjalan.
        $periodeLain = $this->makeStudent('Ditagih Bulan Lalu', $berbayar);
        Payment::create([
            'student_id' => $periodeLain->id,
            'payment_date' => now()->subMonthNoOverflow()->toDateString(),
            'due_date' => now()->subMonthNoOverflow()->addDays(7)->toDateString(),
            'billing_period' => Payment::periodFor(now()->subMonthNoOverflow()),
            'payment_amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $lewatScope = Student::unbilledFor()->pluck('name')->sort()->values()->all();

        $lewatPhp = Student::with(['classes', 'payments'])->get()
            ->filter->isUnbilledThisPeriod()
            ->pluck('name')->sort()->values()->all();

        $this->assertSame(['Ditagih Bulan Lalu', 'Layak Ditagih'], $lewatScope);
        $this->assertSame($lewatScope, $lewatPhp, 'Scope SQL & billingSkipReason() harus selalu sepakat');
    }

    // ─── LABEL & PENYIMPULAN PERIODE ───────────────────────────────

    public function test_period_helpers(): void
    {
        $this->assertSame('2026-08', Payment::periodFor('2026-08-30'));
        $this->assertSame('Agustus 2026', Payment::labelForPeriod('2026-08'));
        $this->assertSame('tanpa periode', Payment::labelForPeriod(null));
    }
}
