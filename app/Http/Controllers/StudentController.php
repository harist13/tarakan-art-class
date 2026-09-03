<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $classId = $request->integer('class_id');
        $unbilled = $request->boolean('unbilled');

        $students = Student::query()
            // `payments` dimuat untuk menandai murid yang terkunci dari modul akademik.
            ->with(['classes', 'payments'])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('parent_name', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->when($classId, fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('classes.id', $classId)))
            ->when($unbilled, fn ($q) => $q->unbilledFor())
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Dihitung lepas dari filter lain: angka pada tombolnya harus menjawab
        // "berapa murid yang belum ditagih bulan ini" secara utuh, bukan
        // "berapa yang belum ditagih di antara hasil pencarian saat ini".
        $unbilledCount = Student::unbilledFor()->count();

        $classes = ClassRoom::orderBy('class_category')->get();

        return view('students.index', compact(
            'students', 'search', 'status', 'classId', 'classes', 'unbilled', 'unbilledCount'
        ));
    }

    public function create(Request $request)
    {
        $classes = ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_category')->get();

        // Slot yang diklik di layar Ketersediaan Slot ikut lewat query string,
        // jadi form terbuka dengan kategori & jadwal itu sudah terpilih — admin
        // tinggal mengisi data anaknya, dan kursi yang tadi dilihat kosong itu
        // pula yang terisi.
        $selectedClass = $classes->firstWhere('id', $request->integer('class_id'));

        return view('students.create', compact('classes', 'selectedClass'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // Pekan mulai bukan kolom murid — ia dicatat pada pendaftarannya ke kelas,
        // jadi dikeluarkan sebelum $data dipakai untuk mengisi model.
        $startWeek = $data['start_week'] ?? null;
        unset($data['start_week']);

        // Jadwal bukan kolom murid — ia pendaftaran ke kelas, jadi dikeluarkan
        // sebelum $data dipakai mengisi model.
        $classId = $data['class_id'] ?? null;
        unset($data['class_id']);

        $matchingClass = $this->resolveClass($classId, $data['class_type']);

        DB::transaction(function () use ($data, $matchingClass, $startWeek) {
            $student = Student::create($data);
            if ($matchingClass) {
                $this->syncClasses($student, [$matchingClass->id], $startWeek);
            }
            ActivityLog::record('created', $student, "Menambahkan murid {$student->name}");
        });

        return redirect()->route('students.index')->with('success', 'Data murid berhasil ditambahkan.');
    }

    public function edit(Student $student)
    {
        $classes = ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_category')->get();
        $student->load('classes');

        $selectedClass = $classes->firstWhere('id', $student->classes->first()?->id);

        return view('students.edit', compact('student', 'classes', 'selectedClass'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $this->validateData($request, $student);

        $startWeek = $data['start_week'] ?? null;
        unset($data['start_week']);

        $classId = $data['class_id'] ?? null;
        unset($data['class_id']);

        // Pindah tipe kelas ditahan selama menunggak — itu keputusan yang bisa
        // ditunda tanpa merusak catatan apa pun. Perubahan data lain tetap boleh.
        $typeChanged = $student->class_type !== $data['class_type'];
        if ($typeChanged && $student->hasArrears()) {
            return back()->withInput()->withErrors([
                'class_type' => "Tipe kelas belum bisa diubah: murid {$student->paymentBlockReason()}. Lunasi dulu di menu Pembayaran.",
            ]);
        }

        // Kelas yang sekarang hanya layak dipertahankan selama kategorinya tak
        // berubah; begitu admin pindah kategori, jadwal lamanya bukan lagi
        // jawaban yang benar.
        $matchingClass = $this->resolveClass(
            $classId,
            $data['class_type'],
            $typeChanged ? null : $student->classes->first()
        );

        DB::transaction(function () use ($student, $data, $matchingClass, $startWeek) {
            $student->update($data);
            if ($matchingClass) {
                $this->syncClasses($student, [$matchingClass->id], $startWeek);
            }
            ActivityLog::record('updated', $student, "Memperbarui murid {$student->name}");
        });

        return redirect()->route('students.index')->with('success', 'Data murid berhasil diperbarui.');
    }

    public function destroy(Student $student)
    {
        // Admin tidak boleh hapus permanen — hanya Super Admin (dijaga di route policy).
        DB::transaction(function () use ($student) {
            ActivityLog::record('deleted', $student, "Menghapus murid {$student->name}");
            $student->delete();
        });

        return redirect()->route('students.index')->with('success', 'Data murid berhasil dihapus.');
    }

    private function validateData(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'parent_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'instagram_username' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'class_type' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($student) {
                    $classes = ClassRoom::whereRaw('LOWER(class_category) = ?', [strtolower($value)])->get();
                    if ($classes->isEmpty()) {
                        return;
                    }

                    if ($student && strtolower($student->class_type) === strtolower($value)) {
                        return;
                    }

                    $hasAvailable = $classes->contains(fn ($c) => $c->isAvailable());
                    if (! $hasAvailable) {
                        $allClosed = $classes->every(fn ($c) => $c->isClosed());
                        $reason = $allClosed ? 'ditutup' : 'penuh';
                        $fail("Kelas untuk kategori {$value} saat ini sedang {$reason}.");
                    }
                },
            ],
            // Jadwal yang dipilih admin. Boleh kosong — resolveClass() lalu
            // memilihkan slot pertama yang masih muat di kategori itu, seperti
            // sebelum form punya pilihan jadwal.
            'class_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($student) {
                    $class = ClassRoom::find($value);
                    if (! $class) {
                        $fail('Jadwal kelas yang dipilih tidak ditemukan.');

                        return;
                    }

                    // Kelas yang sedang diikuti murid ini tetap boleh dipilih walau
                    // sudah penuh — dialah salah satu yang mengisinya.
                    if ($student && $student->classes()->where('classes.id', $class->id)->exists()) {
                        return;
                    }

                    if (! $class->isAvailable()) {
                        $fail("Jadwal {$class->scheduleLabel()} tidak bisa dipilih: {$class->availability()['text']}.");
                    }
                },
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'join_date' => ['required', 'date'],
            // Pekan murid mulai ikut kelas — penentu harga bulan pertamanya.
            // Boleh kosong: syncClasses() menurunkannya dari tanggal bergabung.
            'start_week' => ['nullable', 'integer', Rule::in(ClassRoom::START_WEEKS)],
        ], [
            'class_type.required' => 'Tipe kelas wajib dipilih.',
            'class_type.in' => 'Tipe kelas yang dipilih tidak valid.',
            'phone_number.regex' => 'No HP Wali harus berupa angka.',
            'date_of_birth.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'age.integer' => 'Usia harus berupa angka.',
            'age.min' => 'Usia tidak boleh kurang dari 0.',
            'age.max' => 'Usia tidak masuk akal (maksimal 120).',
        ]);
    }

    /**
     * Kelas yang akan diisi murid ini.
     *
     * Urutannya disengaja: jadwal yang dipilih admin di form menang atas apa pun,
     * lalu kelas yang sedang diikuti murid, baru tebakan "slot pertama yang masih
     * muat di kategori ini". Tebakan itu dipertahankan sebagai jaring pengaman —
     * pendaftaran lewat jalur lain (impor, form lama yang di-cache) tidak boleh
     * gagal hanya karena tak menyertakan jadwal.
     *
     * Pilihan yang kategorinya tak lagi cocok dengan tipe kelas di form diabaikan:
     * mengganti kategori tanpa mengganti jadwal jangan sampai mendaftarkan anak ke
     * kelas kategori lama.
     */
    private function resolveClass(?int $classId, string $category, ?ClassRoom $current = null): ?ClassRoom
    {
        $cocok = fn (?ClassRoom $c) => $c && mb_strtolower(trim((string) $c->class_category)) === mb_strtolower(trim($category));

        if ($classId) {
            $chosen = ClassRoom::find($classId);
            if ($cocok($chosen)) {
                return $chosen;
            }
        }

        if ($cocok($current)) {
            return $current;
        }

        $inCategory = ClassRoom::whereRaw('LOWER(class_category) = ?', [strtolower($category)])->get();

        return $inCategory->first(fn (ClassRoom $c) => $c->isAvailable()) ?? $inCategory->first();
    }

    /**
     * Pasang murid ke kelasnya, berikut pekan ia mulai.
     *
     * Pekan mulai tinggal di pivot, bukan di murid: seorang murid bisa masuk
     * kelas kedua di bulan yang berbeda, dan harga bulan pertama tiap kelas
     * dihitung dari pekannya sendiri.
     *
     * Tanggal daftar dipertahankan bila pendaftarannya sudah ada — menyegarkannya
     * jadi hari ini setiap kali data murid disunting akan menghapus jejak kapan
     * anak itu sebenarnya mulai.
     */
    private function syncClasses(Student $student, array $classIds, ?int $startWeek = null): void
    {
        $classIds = array_filter($classIds);
        $existing = $student->classes()->pluck('student_class.enrolled_at', 'classes.id');
        $payload = [];

        foreach ($classIds as $id) {
            $enrolledAt = $existing[$id] ?? now()->toDateString();

            $payload[$id] = [
                'status' => 'active',
                'enrolled_at' => $enrolledAt,
                'start_week' => $startWeek ?? ClassRoom::weekOfMonth(Carbon::parse($enrolledAt)),
            ];
        }

        $student->classes()->sync($payload);
    }
}
