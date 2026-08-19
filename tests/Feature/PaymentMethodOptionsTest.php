<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: daftar Metode / Channel hanya ditulis sekali.
 *
 * Sebelumnya daftarnya tersalin di empat tempat — dua <select> dan dua
 * Rule::in. Gejala kalau salah satu tertinggal tidak enak dibaca admin:
 * pilihannya ada di layar, tapi ditolak begitu disimpan. Tes ini membandingkan
 * apa yang DITAWARKAN dengan apa yang DITERIMA, di dua halaman yang tersisa:
 * Buat Invoice (menerbitkan) dan Edit (merevisi).
 */
class PaymentMethodOptionsTest extends TestCase
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

    private function makeStudent(string $name): Student
    {
        $tutor = Tutor::create(['name' => 'Kak Tutor', 'status' => 'active']);
        $class = ClassRoom::create([
            'class_name' => 'Kelas Drawing',
            'class_category' => 'drawing',
            'tutor_id' => $tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->addDay()->toDateString(),
            'schedule_time' => '09:00',
            'class_fee' => 150000,
            'status' => 'open',
        ]);

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
        $student->classes()->attach($class->id, ['status' => 'active', 'enrolled_at' => now()->toDateString()]);

        return $student;
    }

    public function test_every_offered_method_is_accepted_by_buat_invoice(): void
    {
        $student = $this->makeStudent('Murid A');
        $methods = array_keys(Payment::methodFormOptions());

        $this->assertNotEmpty($methods);

        foreach ($methods as $index => $method) {
            $this->actingAs($this->admin())
                ->post(route('payments.store'), [
                    // Periode dibedakan tiap metode supaya yang ditolak benar-benar
                    // metodenya, bukan aturan satu invoice per periode.
                    'billing_period' => sprintf('2026-%02d', $index + 1),
                    'payment_date' => '2026-08-01',
                    'due_date' => '2026-08-08',
                    'payment_method' => $method,
                    'payment_status' => 'unpaid',
                    'students' => [$student->id],
                    'amounts' => [$student->id => 150000],
                ])
                ->assertSessionHasNoErrors("Metode '{$method}' ditawarkan di form tapi ditolak validasi");
        }

        $this->assertSame($methods, Payment::orderBy('id')->pluck('payment_method')->all());
    }

    public function test_every_offered_method_is_accepted_by_edit(): void
    {
        $student = $this->makeStudent('Murid A');
        $payment = Payment::create([
            'student_id' => $student->id,
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'billing_period' => '2026-08',
            'payment_amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        // Halaman Edit punya <select> dan Rule::in sendiri — itulah tempat kedua
        // yang dulu ikut menyalin daftar metode.
        foreach (array_keys(Payment::methodFormOptions()) as $method) {
            $this->actingAs($this->admin())
                ->put(route('payments.update', $payment), [
                    'student_id' => $student->id,
                    'payment_date' => '2026-08-01',
                    'due_date' => '2026-08-08',
                    'billing_period' => '2026-08',
                    'payment_amount' => 150000,
                    'payment_method' => $method,
                    'payment_status' => 'unpaid',
                ])
                ->assertSessionHasNoErrors("Metode '{$method}' ditawarkan di Edit tapi ditolak validasi");

            $this->assertSame($method, $payment->fresh()->payment_method);
        }
    }

    public function test_both_pages_offer_exactly_the_same_options(): void
    {
        $student = $this->makeStudent('Murid A');
        $payment = Payment::create([
            'student_id' => $student->id,
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'payment_amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $buatInvoice = $this->actingAs($this->admin())->get(route('payments.create'))->assertOk();
        $edit = $this->actingAs($this->admin())->get(route('payments.edit', $payment))->assertOk();

        foreach (Payment::methodFormOptions() as $value => $label) {
            $buatInvoice->assertSee('value="'.$value.'"', false)->assertSee($label, false);
            $edit->assertSee('value="'.$value.'"', false)->assertSee($label, false);
        }
    }

    // ─── NILAI WARISAN 'ewallet' ───────────────────────────────────

    public function test_legacy_method_still_reads_as_qris(): void
    {
        $payment = Payment::create([
            'student_id' => $this->makeStudent('Murid Lama')->id,
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'payment_amount' => 150000,
            'payment_method' => 'ewallet',
            'payment_status' => 'unpaid',
        ]);

        $this->assertSame('QRIS / E-Wallet', $payment->methodLabel());
        $this->assertSame('qris', Payment::normalizeMethod('ewallet'));
    }

    public function test_editing_a_legacy_invoice_preselects_qris_not_cash(): void
    {
        $payment = Payment::create([
            'student_id' => $this->makeStudent('Murid Lama')->id,
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'payment_amount' => 150000,
            'payment_method' => 'ewallet',
            'payment_status' => 'unpaid',
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('payments.edit', $payment))
            ->assertOk()
            ->getContent();

        // Yang dijaga: membuka lalu menyimpan ulang invoice lama tidak boleh
        // diam-diam mengubah metodenya jadi Cash (opsi pertama).
        $this->assertMatchesRegularExpression('/<option value="qris"[^>]*\bselected\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="cash"[^>]*\bselected\b/', $html);
    }
}
