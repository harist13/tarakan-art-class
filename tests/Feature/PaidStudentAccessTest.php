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
 * QA: gerbang pembayaran untuk modul akademik.
 *
 * Murid hanya boleh muncul & diproses di absensi, raport, dan replacement class
 * bila punya invoice lunas dan tidak menyisakan invoice unpaid.
 */
class PaidStudentAccessTest extends TestCase
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
            'schedule_date' => now()->addWeek()->toDateString(),
            'schedule_time' => '09:00',
            'class_fee' => 100000,
            'status' => 'open',
        ]);
    }

    private function makeStudent(string $name, ?string $paymentStatus = null): Student
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

        if ($paymentStatus) {
            Payment::create([
                'student_id' => $student->id,
                'payment_date' => now()->toDateString(),
                'payment_amount' => 100000,
                'payment_method' => 'cash',
                'payment_status' => $paymentStatus,
            ]);
        }

        return $student;
    }

    // ─── DEFINISI LUNAS ────────────────────────────────────────────

    public function test_paid_status_requires_settled_invoice(): void
    {
        $paid = $this->makeStudent('Lunas', 'paid');
        $unpaid = $this->makeStudent('Nunggak', 'unpaid');
        $noInvoice = $this->makeStudent('Tanpa Invoice');

        $this->assertTrue($paid->isPaid());
        $this->assertFalse($unpaid->isPaid());
        $this->assertFalse($noInvoice->isPaid(), 'Murid tanpa invoice terhitung belum lunas');

        $this->assertSame(['Lunas'], Student::paid()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            ['Nunggak', 'Tanpa Invoice'],
            Student::unpaid()->pluck('name')->all()
        );

        // Punya riwayat lunas tapi menyisakan tunggakan → tetap terkunci.
        Payment::create([
            'student_id' => $paid->id,
            'payment_date' => now()->toDateString(),
            'payment_amount' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $this->assertFalse($paid->fresh()->isPaid());
        $this->assertSame([], Student::paid()->pluck('name')->all());
    }

    // ─── ABSENSI ───────────────────────────────────────────────────

    public function test_attendance_form_only_lists_paid_students(): void
    {
        $class = $this->makeClass();
        $paid = $this->makeStudent('Murid Lunas', 'paid');
        $unpaid = $this->makeStudent('Murid Nunggak', 'unpaid');
        foreach ([$paid, $unpaid] as $student) {
            $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);
        }

        $this->actingAs($this->admin())
            ->get(route('attendances.create', ['class_id' => $class->id]))
            ->assertOk()
            ->assertSee('Murid Lunas')
            ->assertSee('tidak bisa diabsen') // catatan murid tertahan
            ->assertDontSee('name="records[0][student_id]" value="'.$unpaid->id.'"', false);
    }

    public function test_attendance_store_rejects_unpaid_student(): void
    {
        $class = $this->makeClass();
        $unpaid = $this->makeStudent('Murid Nunggak', 'unpaid');

        $this->actingAs($this->admin())
            ->post(route('attendances.store'), [
                'class_id' => $class->id,
                'attendance_date' => now()->toDateString(),
                'records' => [['student_id' => $unpaid->id, 'status' => 'present']],
            ])
            ->assertSessionHasErrors('records.0.student_id');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_attendance_index_hides_rows_of_unpaid_student(): void
    {
        $class = $this->makeClass();
        $paid = $this->makeStudent('Murid Lunas', 'paid');
        $unpaid = $this->makeStudent('Murid Nunggak', 'unpaid');

        foreach ([$paid, $unpaid] as $student) {
            Attendance::create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'attendance_date' => now()->toDateString(),
                'status' => 'present',
            ]);
        }

        $this->actingAs($this->admin())
            ->get(route('attendances.index'))
            ->assertOk()
            ->assertSee('Murid Lunas')
            ->assertDontSee('Murid Nunggak');
    }

    // ─── RAPORT ────────────────────────────────────────────────────

    public function test_report_form_only_lists_paid_students(): void
    {
        $this->makeStudent('Murid Lunas', 'paid');
        $this->makeStudent('Murid Nunggak', 'unpaid');

        $this->actingAs($this->admin())
            ->get(route('reports.create'))
            ->assertOk()
            ->assertSee('Murid Lunas')
            ->assertDontSee('Murid Nunggak');
    }

    public function test_report_store_rejects_unpaid_student(): void
    {
        $unpaid = $this->makeStudent('Murid Nunggak', 'unpaid');

        $this->actingAs($this->admin())
            ->post(route('reports.store'), [
                'student_id' => $unpaid->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'activity_notes' => 'Menggambar',
                'achievement_score' => 85,
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseCount('student_reports', 0);
    }

    public function test_report_index_and_guest_access_hide_unpaid_student(): void
    {
        $unpaid = $this->makeStudent('Murid Nunggak', 'unpaid');
        $report = StudentReport::create([
            'student_id' => $unpaid->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'activity_notes' => 'Menggambar',
            'achievement_score' => 85,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee('Murid Nunggak')
            ->assertSee('disembunyikan karena muridnya belum berstatus lunas', false);

        $this->post(route('reports.guest.show'), ['credential_key' => $report->credential_key])
            ->assertSessionHasErrors('credential_key');
    }

    // ─── REPLACEMENT CLASS ─────────────────────────────────────────

    public function test_replacement_form_only_lists_paid_students(): void
    {
        $this->makeClass();
        $this->makeStudent('Murid Lunas', 'paid');
        $this->makeStudent('Murid Nunggak', 'unpaid');

        $this->actingAs($this->admin())
            ->get(route('schedules.create'))
            ->assertOk()
            ->assertSee('Murid Lunas')
            ->assertDontSee('Murid Nunggak');
    }

    public function test_replacement_store_rejects_unpaid_student(): void
    {
        $class = $this->makeClass();
        $unpaid = $this->makeStudent('Murid Nunggak', 'unpaid');

        $this->actingAs($this->admin())
            ->post(route('schedules.store'), [
                'student_id' => $unpaid->id,
                'class_id' => $class->id,
                'replacement_date' => now()->addWeek()->toDateString(),
                'replacement_time' => '09:00',
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseCount('replacement_requests', 0);
    }

    public function test_replacement_index_hides_request_of_unpaid_student(): void
    {
        $class = $this->makeClass();
        $paid = $this->makeStudent('Murid Lunas', 'paid');
        $unpaid = $this->makeStudent('Murid Nunggak', 'unpaid');

        foreach ([$paid, $unpaid] as $student) {
            ReplacementRequest::create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'replacement_date' => now()->addWeek()->toDateString(),
                'replacement_time' => '09:00',
                'request_status' => 'pending',
            ]);
        }

        $this->actingAs($this->admin())
            ->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Murid Lunas')
            ->assertDontSee('Murid Nunggak');
    }

    // ─── PULIH SETELAH DILUNASI ────────────────────────────────────

    public function test_student_reappears_after_invoice_confirmed_paid(): void
    {
        $student = $this->makeStudent('Murid Nunggak', 'unpaid');
        $payment = Payment::where('student_id', $student->id)->first();

        $this->actingAs($this->admin())
            ->get(route('reports.create'))
            ->assertDontSee('Murid Nunggak');

        $this->actingAs($this->admin())->patch(route('payments.confirm', $payment));

        $this->actingAs($this->admin())
            ->get(route('reports.create'))
            ->assertSee('Murid Nunggak');
    }
}
