<?php

namespace Tests\Feature;

use App\Console\Commands\ImportStudentsCsv;
use App\Models\ClassRoom;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Impor murid, jadwal, & invoice dari CSV spreadsheet sanggar (students:import-csv).
 */
class ImportStudentsCsvTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csv = tempnam(sys_get_temp_dir(), 'murid');
        file_put_contents($this->csv, <<<'CSV'
Nama Murid,Nama Transfer,Nama Ortu WA,Instagram Wali,Nomor Hp,Nominal ditagihkan,Status Pembayaran,Pembayaran ,Tanggal Pembayaran,Course,Jadwal,Notes
Ahsan,Jumiati,Jumiati,@jumiati_ishak,62 811-5411-504,"Rp360,000",Belum Bayar,,,Basic Mewarnai,"Sabtu, 12.30-2",
Alhanan,Siti Maliha,,smaliha1,0811 5351 007,"Rp360,000",Belum Bayar,,,Basic Mewarnai,"Sabtu, 12.30-2",
Graziella,Andrew,Cintya,,0822 4796 9067,"Rp720,000",Belum Bayar,,,Basic Mewarnai,"Kamis, 1.30-3
Jumat, 3-4.30",
Brian,Dessy,Dessy,,,"Rp400,000",Belum Bayar,,,Pre-school,"Senin, 4-5",
Celine,Melisa,Melisa,,0821 3295 5660,"Rp360,000",Off,,,Basic Sketch,,
Dira,,Sri,,0811 5392 388 (0812 9441 8535),"Rp610,000",Belum Bayar,,,Basic Sketch,"Sabtu, 9.30 - 11",
CSV);
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);

        parent::tearDown();
    }

    private function import(array $options = []): void
    {
        // 7 September 2026 adalah Senin.
        $this->artisan('students:import-csv', [
            'file' => $this->csv,
            '--mulai' => '2026-09-07',
            '--periode' => '2026-09',
        ] + $options)->assertSuccessful();
    }

    public function test_reads_conversational_schedule_times(): void
    {
        $this->assertSame(['day' => 6, 'start' => '12:30', 'end' => '14:00'], ImportStudentsCsv::parseSchedule('Sabtu, 12.30-2'));
        $this->assertSame(['day' => 6, 'start' => '09:30', 'end' => '11:00'], ImportStudentsCsv::parseSchedule('Sabtu, 9.30 - 11'));
        $this->assertSame(['day' => 2, 'start' => '13:30', 'end' => '15:00'], ImportStudentsCsv::parseSchedule('Selasa,1.30 - 3'));
        $this->assertSame(['day' => 2, 'start' => '16:30', 'end' => '18:00'], ImportStudentsCsv::parseSchedule('Selasa, 4.30 -6'));
        $this->assertSame(['day' => 3, 'start' => '10:00', 'end' => '11:30'], ImportStudentsCsv::parseSchedule('Rabu, 10-11.30'));
        $this->assertSame(['day' => 1, 'start' => '16:00', 'end' => '17:00'], ImportStudentsCsv::parseSchedule('Senin, 4-5'));
        $this->assertNull(ImportStudentsCsv::parseSchedule('Jumat, '));
    }

    public function test_imports_students_into_regular_weekly_classes(): void
    {
        $this->import();

        $this->assertSame(6, Student::count());
        $this->assertSame(5, ClassRoom::count());
        $this->assertTrue(ClassRoom::all()->every(fn (ClassRoom $c) => $c->class_type === 'regular' && $c->is_recurring));

        $sabtu = ClassRoom::where('class_category', 'Basic Mewarnai')->where('schedule_time', '12:30:00')->sole();
        $this->assertSame('14:00', $sabtu->endTimeLabel());
        $this->assertSame(6, $sabtu->day_of_week);
        $this->assertSame('2026-09-12', $sabtu->schedule_date->toDateString());
        $this->assertEquals(360000, (float) $sabtu->class_fee);
        $this->assertSame(2, $sabtu->enrolledCount());
        $this->assertSame(ImportStudentsCsv::PLACEHOLDER_TUTOR, $sabtu->tutor->name);

        $preschool = ClassRoom::where('class_category', 'Pre-school')->sole();
        $this->assertSame('16:00–17:00', $preschool->timeRangeLabel());
        $this->assertEquals(400000, (float) $preschool->class_fee);

        $ahsan = Student::where('name', 'Ahsan')->sole();
        $this->assertSame('Jumiati', $ahsan->parent_name);
        $this->assertSame('08115411504', $ahsan->phone_number);
        $this->assertSame('jumiati_ishak', $ahsan->instagram_username);
        $this->assertSame('Basic Mewarnai', $ahsan->class_type);
        $this->assertSame('active', $ahsan->status);
        $this->assertNull($ahsan->date_of_birth);
        $this->assertSame(1, (int) $ahsan->classes->first()->pivot->start_week);
        $this->assertSame('2026-09-07', $ahsan->join_date->toDateString());

        // Nama ortu kosong → nama pengirim transfer.
        $this->assertSame('Siti Maliha', Student::where('name', 'Alhanan')->sole()->parent_name);

        // Dua baris jadwal dalam satu sel → dua kelas.
        $this->assertSame(
            ['Kamis', 'Jumat'],
            Student::where('name', 'Graziella')->sole()->classes->sortBy('day_of_week')->map->dayName()->values()->all()
        );

        $celine = Student::where('name', 'Celine')->sole();
        $this->assertSame('inactive', $celine->status);
        $this->assertCount(0, $celine->classes);

        $this->assertSame('08115392388', Student::where('name', 'Dira')->sole()->phone_number);
    }

    public function test_issues_unpaid_invoices_with_spreadsheet_amounts(): void
    {
        $this->import();

        // Semua "Belum Bayar"; Celine (Off) tidak ditagih.
        $this->assertSame(5, Payment::count());
        $this->assertFalse(Student::where('name', 'Celine')->sole()->payments()->exists());

        $graziella = Student::where('name', 'Graziella')->sole()->payments()->sole();
        $this->assertSame('unpaid', $graziella->payment_status);
        $this->assertSame('2026-09', $graziella->billing_period);
        $this->assertEquals(720000, (float) $graziella->payment_amount);
        $this->assertSame(today()->toDateString(), $graziella->payment_date->toDateString());
        $this->assertSame(Payment::defaultDueDate(), $graziella->due_date->toDateString());
        $this->assertSame('transfer', $graziella->payment_method);

        // Nominal spreadsheet dipakai apa adanya, meski berbeda dari iuran kelas.
        $this->assertEquals(610000, (float) Student::where('name', 'Dira')->sole()->payments()->sole()->payment_amount);

        // Invoice Unpaid tidak menjadi pemasukan & belum menunggak.
        $this->assertFalse(Student::inArrears()->exists());
    }

    public function test_issues_invoices_for_students_imported_earlier(): void
    {
        $this->import(['--tanpa-tagihan' => true]);
        $this->assertSame(0, Payment::count());

        $this->import();

        $this->assertSame(6, Student::count());
        $this->assertSame(5, Payment::count());
    }

    public function test_running_twice_does_not_duplicate(): void
    {
        $this->import();
        $this->import();

        $this->assertSame(6, Student::count());
        $this->assertSame(5, ClassRoom::count());
        $this->assertSame(1, Tutor::count());
        $this->assertSame(5, Payment::count());
    }

    public function test_dry_run_saves_nothing(): void
    {
        $nextInvoice = NumberSequence::where('name', 'invoice')->value('next_number');

        $this->import(['--dry-run' => true]);

        $this->assertSame(0, Student::count());
        $this->assertSame(0, ClassRoom::count());
        $this->assertSame(0, Tutor::count());
        $this->assertSame(0, Payment::count());
        // Nomor invoice tidak ikut terpakai.
        $this->assertSame($nextInvoice, NumberSequence::where('name', 'invoice')->value('next_number'));
    }

    public function test_rejects_due_date_in_the_past(): void
    {
        $this->artisan('students:import-csv', [
            'file' => $this->csv,
            '--jatuh-tempo' => today()->subDay()->toDateString(),
        ])->assertFailed();

        $this->assertSame(0, Student::count());
    }
}
