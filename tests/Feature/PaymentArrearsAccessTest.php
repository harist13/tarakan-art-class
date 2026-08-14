<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: gerbang pembayaran bertingkat.
 *
 *   invoice belum jatuh tempo → tidak menghalangi apa pun
 *   lewat jatuh tempo         → kelas pengganti, pindah kelas, & akses raport
 *                               orang tua ditahan; absensi TETAP jalan
 *   lewat masa toleransi      → murid ditangguhkan dari daftar kelas, tapi
 *                               seluruh datanya tetap ada
 */
class PaymentArrearsAccessTest extends TestCase
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

    private function makeClass(string $category = 'drawing'): ClassRoom
    {
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'active']);

        return ClassRoom::create([
            'class_name' => 'Kelas '.ucfirst($category),
            'class_category' => $category,
            'tutor_id' => $tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->addDay()->toDateString(),
            'schedule_time' => '09:00',
            'class_fee' => 100000,
            'status' => 'open',
        ]);
    }

    private function makeStudent(string $name): Student
    {
        return Student::create([
            'name' => $name,
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => '081234567890',
            'address' => 'Jl. Mawar 1',
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => '2026-01-15',
        ]);
    }

    /** Invoice dengan jatuh tempo relatif hari ini (negatif = sudah lewat). */
    private function invoice(Student $student, string $status, int $dueInDays): Payment
    {
        return Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->subDays(max(0, -$dueInDays))->toDateString(),
            'due_date' => now()->addDays($dueInDays)->toDateString(),
            'payment_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => $status,
        ]);
    }

    // ─── DEFINISI MENUNGGAK ────────────────────────────────────────

    public function test_unpaid_invoice_before_due_date_is_not_arrears(): void
    {
        $student = $this->makeStudent('Murid Rajin');
        $this->invoice($student, 'paid', -30);   // SPP bulan lalu, lunas
        $this->invoice($student, 'unpaid', 7);   // SPP bulan ini, baru terbit

        $this->assertFalse($student->fresh()->hasArrears(), 'Invoice yang belum jatuh tempo bukan tunggakan');
        $this->assertSame(['Murid Rajin'], Student::settled()->pluck('name')->all());
        $this->assertSame([], Student::inArrears()->pluck('name')->all());
    }

    public function test_overdue_invoice_is_arrears(): void
    {
        $student = $this->makeStudent('Murid Nunggak');
        $this->invoice($student, 'unpaid', -10);

        $student = $student->fresh();
        $this->assertTrue($student->hasArrears());
        $this->assertSame(10, $student->arrearsDays());
        $this->assertSame(['Murid Nunggak'], Student::inArrears()->pluck('name')->all());
    }

    public function test_student_without_invoice_is_not_locked_but_is_flagged(): void
    {
        $student = $this->makeStudent('Murid Baru');

        $this->assertFalse($student->hasArrears(), 'Belum ada invoice bukan alasan mengunci akademik');
        $this->assertFalse($student->isActivated());
        $this->assertSame('Belum bayar pendaftaran', $student->paymentBadgeLabel());
    }

    public function test_due_date_defaults_from_config(): void
    {
        config(['academic.payment.due_days' => 7]);
        $student = $this->makeStudent('Murid Baru');

        $payment = Payment::create([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'payment_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $this->assertSame(now()->addDays(7)->toDateString(), $payment->due_date->toDateString());
    }

    // ─── ABSENSI: TIDAK PERNAH DIGERBANG ───────────────────────────

    public function test_attendance_form_lists_student_in_arrears(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent('Murid Nunggak');
        $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);
        $this->invoice($student, 'unpaid', -10);

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $class->id]))
            ->assertOk()
            ->assertSee('Murid Nunggak')
            ->assertSee('name="records[0][student_id]" value="'.$student->id.'"', false)
            ->assertSee('Menunggak 10 hari');
    }

    public function test_attendance_store_accepts_student_in_arrears(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent('Murid Nunggak');
        $this->invoice($student, 'unpaid', -10);

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $class->id,
                'attendance_date' => now()->toDateString(),
                'records' => [['student_id' => $student->id, 'status' => 'present']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_attendance_index_shows_rows_of_student_in_arrears(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent('Murid Nunggak');
        $this->invoice($student, 'unpaid', -10);

        Attendance::create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $this->actingAs($this->admin())
            ->get(route('attendances.index'))
            ->assertOk()
            ->assertSee('Murid Nunggak');
    }

    // ─── RAPORT: DISUSUN BEBAS, DIRILIS SAAT LUNAS ─────────────────

    public function test_report_can_be_created_for_student_in_arrears(): void
    {
        $student = $this->makeStudent('Murid Nunggak');
        $this->invoice($student, 'unpaid', -10);

        $this->actingAs($this->admin())
            ->get(route('reports.create'))
            ->assertOk()
            ->assertSee('Murid Nunggak');

        $this->actingAs($this->admin())
            ->post(route('reports.store'), [
                'student_id' => $student->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'activity_notes' => 'Menggambar',
                'achievement_score' => 85,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_reports', 1);
    }

    public function test_guest_report_is_withheld_while_in_arrears_and_released_after_payment(): void
    {
        $student = $this->makeStudent('Murid Nunggak');
        $payment = $this->invoice($student, 'unpaid', -10);
        $report = StudentReport::create([
            'student_id' => $student->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'activity_notes' => 'Menggambar',
            'achievement_score' => 85,
        ]);

        // Admin tetap melihat raportnya; yang ditahan hanya akses orang tua.
        $this->actingAs($this->admin())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Murid Nunggak')
            ->assertSee('lewat jatuh tempo', false);

        $this->post(route('reports.guest.show'), ['credential_key' => $report->credential_key])
            ->assertSessionHasErrors('credential_key');

        $this->actingAs($this->admin())->patch(route('payments.confirm', $payment));

        $this->post(route('reports.guest.show'), ['credential_key' => $report->credential_key])
            ->assertSessionHasNoErrors();
    }

    // ─── KELAS PENGGANTI & PINDAH KELAS: DITAHAN ───────────────────

    public function test_replacement_rejects_student_in_arrears(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent('Murid Nunggak');
        $this->invoice($student, 'unpaid', -10);

        $this->actingAs($this->admin())
            ->get(route('schedules.create'))
            ->assertOk()
            ->assertDontSee('Murid Nunggak');

        $this->actingAs($this->admin())
            ->post(route('schedules.store'), [
                'student_id' => $student->id,
                'class_id' => $class->id,
                'replacement_date' => now()->addWeek()->toDateString(),
                'replacement_time' => '09:00',
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseCount('replacement_requests', 0);
    }

    public function test_replacement_allows_student_with_invoice_not_yet_due(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent('Murid Rajin');
        $this->invoice($student, 'unpaid', 7);
        $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);

        $this->actingAs($this->admin())
            ->get(route('schedules.create'))
            ->assertOk()
            ->assertSee('Murid Rajin');
    }

    public function test_replacement_index_still_shows_existing_request_of_student_in_arrears(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent('Murid Nunggak');
        $this->invoice($student, 'unpaid', -10);

        ReplacementRequest::create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'replacement_date' => now()->addWeek()->toDateString(),
            'replacement_time' => '09:00',
            'request_status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Murid Nunggak');
    }

    public function test_class_change_blocked_while_in_arrears(): void
    {
        $classA = $this->makeClass();
        $classB = $this->makeClass('coloring');
        $student = $this->makeStudent('Murid Nunggak');
        $student->classes()->attach($classA->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);
        $this->invoice($student, 'unpaid', -10);

        $this->actingAs($this->admin())
            ->put(route('students.update', $student), [
                'name' => 'Murid Nunggak',
                'date_of_birth' => '2018-05-10',
                'parent_name' => 'Ibu Ani',
                'phone_number' => '081234567890',
                'class_type' => 'drawing',
                'status' => 'active',
                'join_date' => '2026-01-15',
                'class_id' => $classB->id,
            ])
            ->assertSessionHasErrors('class_id');

        $this->assertSame([$classA->id], $student->fresh()->classes->pluck('id')->all());
    }

    // ─── PENANGGUHAN OTOMATIS ──────────────────────────────────────

    public function test_command_suspends_only_after_grace_period(): void
    {
        config(['academic.payment.grace_days' => 14]);

        $fresh = $this->makeStudent('Nunggak Baru');
        $this->invoice($fresh, 'unpaid', -3);

        $old = $this->makeStudent('Nunggak Lama');
        $this->invoice($old, 'unpaid', -30);

        $this->artisan('students:suspend-overdue')->assertSuccessful();

        $this->assertFalse($fresh->fresh()->isSuspended(), 'Masih dalam masa toleransi');
        $this->assertTrue($old->fresh()->isSuspended());
        $this->assertStringContainsString('lewat jatuh tempo', (string) $old->fresh()->suspended_reason);
    }

    public function test_suspended_student_leaves_attendance_roster_but_keeps_history(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent('Murid Ditangguhkan');
        $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);
        $this->invoice($student, 'unpaid', -30);

        Attendance::create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'attendance_date' => now()->subWeek()->toDateString(),
            'status' => 'present',
        ]);

        $this->artisan('students:suspend-overdue')->assertSuccessful();

        // Keluar dari daftar absensi kelas berikutnya…
        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $class->id]))
            ->assertOk()
            ->assertSee('sedang ditangguhkan karena tunggakan')
            ->assertDontSee('name="records[0][student_id]" value="'.$student->id.'"', false);

        // …tapi histori kehadirannya tetap utuh & terlihat.
        $this->actingAs($this->admin())
            ->get(route('attendances.index'))
            ->assertOk()
            ->assertSee('Murid Ditangguhkan');
    }

    public function test_paying_the_invoice_lifts_suspension_immediately(): void
    {
        $student = $this->makeStudent('Murid Ditangguhkan');
        $payment = $this->invoice($student, 'unpaid', -30);

        $this->artisan('students:suspend-overdue')->assertSuccessful();
        $this->assertTrue($student->fresh()->isSuspended());

        $this->actingAs($this->admin())->patch(route('payments.confirm', $payment));

        $student = $student->fresh();
        $this->assertFalse($student->isSuspended(), 'Penangguhan dicabut begitu invoice dikonfirmasi lunas');
        $this->assertNull($student->suspended_reason);
    }

    /** Smoke test halaman yang ikut berubah kolom/filternya. */
    public function test_payment_and_calendar_pages_render_with_due_date_column(): void
    {
        $this->makeClass();
        $student = $this->makeStudent('Murid Nunggak');
        $this->invoice($student, 'unpaid', -10);

        $this->actingAs($this->admin())
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Jatuh Tempo')
            ->assertSee('Lewat 10 hari');

        $this->actingAs($this->admin())
            ->get(route('payments.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertSee('Murid Nunggak');

        $this->actingAs($this->admin())->get(route('payments.create'))->assertOk();
        $this->actingAs($this->admin())->get(route('schedules.calendar'))->assertOk();

        $this->actingAs($this->admin())
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Menunggak 10 hari');
    }

    public function test_command_restores_student_whose_arrears_were_voided(): void
    {
        $student = $this->makeStudent('Murid Ditangguhkan');
        $student->suspend('punya 1 invoice lewat jatuh tempo (terlama 30 hari)');

        $this->artisan('students:suspend-overdue')->assertSuccessful();

        $this->assertFalse($student->fresh()->isSuspended());
    }
}
