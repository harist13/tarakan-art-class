<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClassRoom;
use App\Models\ReplacementRequest;
use App\Models\Tutor;
use App\Support\ScheduleCalendar;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClassRoomController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Paginasi manual untuk daftar yang sudah disaring di PHP.
     *
     * Dipakai saat filter "Hari" aktif, karena hari tidak lagi tersimpan sebagai
     * kolom sehingga tak bisa disaring di level query.
     *
     * @param  Collection<int, ClassRoom>  $items
     * @return LengthAwarePaginator<int, ClassRoom>
     */
    private function paginateFiltered(Collection $items, Request $request): LengthAwarePaginator
    {
        $items = $items->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, self::PER_PAGE)->values(),
            $items->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * Daftar kelas untuk panel "Manajemen kelas".
     *
     * Dipisah dari index() karena panel itu kini tidak lagi punya tombol di
     * layar — hanya dijangkau lewat ?tab=kelas. Menjalankan query beserta
     * seluruh filternya di tiap kunjungan kalender berarti membayar untuk
     * daftar yang tak pernah dirender.
     *
     * @return LengthAwarePaginator<int, ClassRoom>
     */
    private function classList(Request $request, string $search, string $category, string $status, string $day): LengthAwarePaginator
    {
        // Subquery jumlah murid aktif per kelas (untuk membandingkan dengan kapasitas).
        $enrolledSql = '(select count(*) from student_class where student_class.class_id = classes.id and student_class.status = ?)';

        $query = ClassRoom::query()
            ->with('tutor')
            ->withCount([
                'students',
                'students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active'),
            ])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('class_category', 'like', "%{$search}%")
                    ->orWhere('class_code', 'like', "%{$search}%");
            }))
            ->when($category !== '', fn ($q) => $q->where('class_category', $category))
            // Status ketersediaan — mengikuti prioritas badge: ditutup > tutor kosong > penuh > tersedia.
            // Tidak ada lagi status "sudah lewat": slot mingguan tidak kedaluwarsa.
            ->when($status === 'ditutup', fn ($q) => $q->where('classes.status', 'closed'))
            ->when($status === 'tanpa-tutor', fn ($q) => $q->where('classes.status', '!=', 'closed')
                ->whereDoesntHave('tutor'))
            ->when($status === 'penuh', fn ($q) => $q->where('classes.status', '!=', 'closed')
                ->whereHas('tutor')
                ->whereRaw("{$enrolledSql} >= classes.capacity", ['active']))
            ->when($status === 'tersedia', fn ($q) => $q->where('classes.status', '!=', 'closed')
                ->whereHas('tutor')
                ->whereRaw("{$enrolledSql} < classes.capacity", ['active']))
            // Kelas yang baru dibuat tampil paling atas; id menurun jadi pemecah
            // kalau ada beberapa kelas yang dibuat pada detik yang sama.
            ->orderByDesc('classes.created_at')
            ->orderByDesc('classes.id');

        // Filter hari disaring di PHP, bukan lewat WHERE: `day_of_week` kini
        // diturunkan dari schedule_date, dan ekspresi hari di SQL berbeda antara
        // MySQL & SQLite — cabang raw per driver berarti cabang produksi tak
        // pernah teruji. Jumlah kelas di sanggar kecil, jadi ini tak terasa.
        //
        // Saat satu hari dipilih, hasilnya diurutkan per jam: pertanyaan yang
        // sedang dijawab adalah "kelas apa saja yang jalan hari ini", jadi yang
        // berguna adalah urutan jadwal — bukan kelas terbaru seperti daftar penuh.
        // Kelas sekali jalan yang tanggalnya sudah lewat ikut disaring keluar:
        // pertanyaannya "apa yang jalan hari Senin", bukan "apa yang pernah jalan".
        // Kelas mingguan selalu punya sesi berikutnya, jadi tak pernah tersaring.
        // Daftar tanpa filter tetap menampilkan semuanya — itu inventaris kelas.
        return ($day !== '' && is_numeric($day))
            ? $this->paginateFiltered(
                $query->get()
                    ->filter(fn (ClassRoom $c) => $c->day_of_week === (int) $day && $c->nextOccurrence() !== null)
                    ->sortBy(fn (ClassRoom $c) => $c->timeLabel()),
                $request
            )
            : $query->paginate(self::PER_PAGE)->withQueryString();
    }

    public function index(Request $request)
    {
        // Panel aktif: 'kalender' (default), 'tutor', atau 'kelas'.
        //
        // Kalender yang jadi pintu masuk, bukan lagi tabel kelas: yang dicari
        // admin saat membuka layar ini adalah "Rabu jam 9 siapa yang mengajar
        // dan kelas apa", dan daftar baris menjawabnya paling lambat. Tabelnya
        // tidak dibuang — masih hidup di ?tab=kelas beserta seluruh CRUD-nya —
        // tapi tidak lagi punya tombol yang menuju ke sana.
        $tab = in_array($request->string('tab')->toString(), ['tutor', 'kelas'], true)
            ? $request->string('tab')->toString()
            : 'kalender';

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();     // tersedia | penuh | tanpa-tutor | ditutup
        $category = $request->string('category')->toString();  // salah satu class_category yang ada; '' = semua
        // Hari mingguan slot; '' = semua. '0' valid (Minggu), jadi dibandingkan sebagai string.
        $day = $request->string('day')->toString();

        // Paginator kosong saat panelnya tidak dirender: view tetap menerima
        // $classes, jadi tak perlu cabang lain di sana.
        $classes = $tab === 'kelas'
            ? $this->classList($request, $search, $category, $status, $day)
            : $this->paginateFiltered(collect(), $request);

        // Filter panel tutor: cari nama/HP, status, & kelas yang diampu.
        $tutorSearch = $request->string('tutor_search')->toString();
        $tutorClassId = $request->integer('tutor_class');
        $tutorStatus = $request->string('tutor_status')->toString();

        // `withActiveStudents` memuat rantai kelas → murid sekali untuk seluruh
        // daftar: kolom "Murid Diampu" dan rinciannya membaca dari sana. Rantai
        // itu yang paling mahal di layar ini, jadi ikut menunggu panelnya dibuka.
        $tutors = $tab !== 'tutor' ? collect() : Tutor::withActiveStudents()
            ->withCount('classes')
            ->when($tutorSearch, fn ($q) => $q->where(function ($sub) use ($tutorSearch) {
                $sub->where('name', 'like', "%{$tutorSearch}%")
                    ->orWhere('phone_number', 'like', "%{$tutorSearch}%");
            }))
            ->when(in_array($tutorStatus, ['full-time', 'part-time'], true), fn ($q) => $q->where('status', $tutorStatus))
            ->when($tutorClassId, fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('id', $tutorClassId)))
            // Tutor yang baru ditambahkan tampil paling atas, sama seperti daftar kelas.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // Daftar semua kelas untuk dropdown filter "kelas yang diampu".
        $allClasses = ClassRoom::orderBy('class_category')->get(['id', 'class_category', 'class_code']);

        // Daftar kategori unik untuk dropdown filter.
        $categories = ClassRoom::query()->distinct()->orderBy('class_category')->pluck('class_category');

        // Kalender merentangkan tiap slot mingguan jadi ratusan kejadian, jadi
        // hanya disusun saat panelnya memang yang dibuka. Karena ia kini panel
        // bawaan, itu berarti hampir tiap kunjungan — tab tutor & kelas yang
        // gantian tidak menyusunnya.
        $calendarEvents = [];
        $calendarStudents = collect();
        $calendarRosters = [];

        if ($tab === 'kalender') {
            $calendar = app(ScheduleCalendar::class);
            $calendarEvents = $calendar->events();
            $calendarStudents = $calendar->students();
            $calendarRosters = $calendar->rosters();
        }

        return view('classes.index', compact(
            'classes', 'search', 'tutors', 'status', 'category', 'day',
            'tab', 'tutorSearch', 'tutorClassId', 'tutorStatus', 'allClasses', 'categories',
            'calendarEvents', 'calendarStudents', 'calendarRosters'
        ));
    }

    public function create()
    {
        $tutors = Tutor::orderBy('name')->get();

        return view('classes.create', compact('tutors'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $class = ClassRoom::create($data);
            ActivityLog::record('created', $class, "Membuat kelas {$class->class_category}");
        });

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil dibuat.');
    }

    public function edit(ClassRoom $class)
    {
        $tutors = Tutor::orderBy('name')->get();

        return view('classes.edit', compact('class', 'tutors'));
    }

    public function update(Request $request, ClassRoom $class)
    {
        $data = $this->validateData($request, $class);

        DB::transaction(function () use ($class, $data) {
            $class->update($data);
            ActivityLog::record('updated', $class, "Memperbarui kelas {$class->class_category}");
        });

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil diperbarui.');
    }

    /**
     * Hapus kelas — hanya yang sudah benar-benar kosong.
     *
     * Kunci asing yang menunjuk kelas memakai cascade: sekali kelas hilang,
     * pendaftaran murid, absensi, dan pengajuan replacement-nya ikut terhapus
     * diam-diam. Riwayat semacam itu tidak bisa dipulihkan, jadi kelas yang
     * masih memegangnya ditahan di sini dan admin diminta memindahkan isinya
     * lebih dulu.
     */
    public function destroy(ClassRoom $class)
    {
        if ($penghalang = $this->deletionBlocker($class)) {
            return back(fallback: route('classes.index'))->with('error', $penghalang);
        }

        DB::transaction(function () use ($class) {
            ActivityLog::record('deleted', $class, "Menghapus kelas {$class->class_category}");
            $class->delete();
        });

        // Kembali ke halaman asal: hapus bisa ditekan dari daftar kelas maupun
        // dari pop-up kalender, dan masing-masing ingin melihat hasilnya di
        // tempatnya sendiri, bukan dilempar ke daftar kelas.
        return back(fallback: route('classes.index'))->with('success', 'Kelas berhasil dihapus.');
    }

    /**
     * Alasan sebuah kelas tak boleh dihapus, atau null kalau aman dihapus.
     */
    private function deletionBlocker(ClassRoom $class): ?string
    {
        $nama = $class->class_category.' ('.$class->class_code.')';

        $murid = $class->students()->count();
        if ($murid > 0) {
            return "Maaf, kelas {$nama} masih memiliki {$murid} murid terdaftar dan belum bisa dihapus. "
                .'Pindahkan muridnya ke kelas lain terlebih dahulu, atau tutup kelas ini agar tidak menerima murid baru.';
        }

        if ($class->attendances()->exists()) {
            return "Maaf, kelas {$nama} sudah punya riwayat absensi dan belum bisa dihapus. "
                .'Menghapusnya akan menghilangkan catatan kehadiran murid. Tutup kelas ini saja bila sudah tidak dipakai.';
        }

        // Replacement bisa menempel sebagai kelas asal maupun kelas tujuan;
        // dua-duanya kehilangan jejak kalau kelasnya dihapus.
        if ($class->replacementRequests()->exists()
            || ReplacementRequest::where('origin_class_id', $class->id)->exists()) {
            return "Maaf, kelas {$nama} masih terpakai pada pengajuan replacement dan belum bisa dihapus. "
                .'Selesaikan atau batalkan pengajuannya terlebih dahulu.';
        }

        return null;
    }

    /**
     * Buka/tutup kelas untuk penerimaan murid & replacement.
     */
    public function toggleStatus(Request $request, ClassRoom $class)
    {
        $closing = $class->status !== 'closed';

        if ($closing) {
            // Alasan opsional tapi disarankan, agar admin lain paham kenapa slot ditutup.
            $reason = $request->validate([
                'closed_reason' => ['nullable', 'string', 'max:255'],
            ])['closed_reason'] ?? null;

            $class->status = 'closed';
            $class->closed_reason = $reason;
        } else {
            $class->status = 'open';
            $class->closed_reason = null; // bersihkan alasan saat slot dibuka kembali
        }

        $label = $closing ? 'ditutup' : 'dibuka';

        DB::transaction(function () use ($class, $label) {
            $class->save();
            ActivityLog::record('updated', $class, "Kelas {$class->class_category} {$label}");
        });

        return back()->with('success', "Kelas {$class->class_category} berhasil {$label}.");
    }

    // ─── Tutor management (nested under Class Management) ──────────

    public function storeTutor(Request $request)
    {
        $data = $this->validateTutor($request);

        DB::transaction(function () use ($data) {
            $tutor = Tutor::create($data);
            ActivityLog::record('created', $tutor, "Menambah tutor {$tutor->name}");
        });

        return redirect()->route('classes.index', ['tab' => 'tutor'])->with('success', 'Tutor berhasil ditambahkan.');
    }

    public function updateTutor(Request $request, Tutor $tutor)
    {
        $data = $this->validateTutor($request);

        DB::transaction(function () use ($tutor, $data) {
            $tutor->update($data);
            ActivityLog::record('updated', $tutor, "Memperbarui tutor {$tutor->name}");
        });

        return redirect()->route('classes.index', ['tab' => 'tutor'])->with('success', 'Data tutor berhasil diperbarui.');
    }

    public function destroyTutor(Tutor $tutor)
    {
        // Tutor yang masih mengampu kelas tidak boleh dihapus.
        if ($tutor->classes()->exists()) {
            return redirect()->route('classes.index', ['tab' => 'tutor'])
                ->with('error', "Tutor {$tutor->name} masih mengampu kelas. Pindahkan kelasnya terlebih dahulu.");
        }

        DB::transaction(function () use ($tutor) {
            ActivityLog::record('deleted', $tutor, "Menghapus tutor {$tutor->name}");
            $tutor->delete();
        });

        return redirect()->route('classes.index', ['tab' => 'tutor'])->with('success', 'Tutor berhasil dihapus.');
    }

    private function validateTutor(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['full-time', 'part-time'])],
        ]);
    }

    private function validateData(Request $request, ?ClassRoom $class = null): array
    {
        $data = $request->validate([
            'class_category' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes', 'class_category')->ignore($class?->id),
            ],
            'tutor_id' => ['required', 'exists:tutors,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            // Jadwal: tanggal + jam. `day_of_week` sengaja tidak divalidasi karena
            // bukan isian admin — ClassRoom menurunkannya dari schedule_date.
            'schedule_date' => ['required', 'date'],
            'schedule_time' => ['required'],
            // Jam selesai wajib untuk kelas yang baru disimpan — kalender tidak
            // bisa menggambarkan sesi sebagai rentang tanpanya. Kolomnya sendiri
            // nullable demi slot lama yang belum sempat mengisinya.
            'schedule_end_time' => ['required', 'after:schedule_time'],
            'class_type' => ['required', Rule::in(array_keys(ClassRoom::TYPE_LABELS))],
            'class_fee' => ['required', 'numeric', 'min:0'],
            // Uang pendaftaran bersifat add-on: kelas yang tidak memungutnya cukup
            // dikosongkan, dan tersimpan sebagai 0.
            'registration_fee' => ['nullable', 'numeric', 'min:0'],
        ], [
            'class_category.unique' => 'Kelas sudah ada.',
            'schedule_date.required' => 'Tanggal kelas belum diisi.',
            'schedule_end_time.required' => 'Jam selesai belum diisi.',
            'schedule_end_time.after' => 'Jam selesai harus lebih malam dari jam mulai.',
            'class_type.required' => 'Tipe kelas belum dipilih.',
        ]);

        // Pengulangan bukan lagi isian tersendiri: trial class hanya berjalan sekali,
        // kelas reguler berulang tiap pekan. Seluruh perhitungan sesi di ClassRoom
        // tetap membaca `is_recurring`, jadi nilainya diturunkan di sini.
        $data['is_recurring'] = $data['class_type'] !== 'trial';
        $data['registration_fee'] = $data['registration_fee'] ?? 0;

        return $data;
    }
}
