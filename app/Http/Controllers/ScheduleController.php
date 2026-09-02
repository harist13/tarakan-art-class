<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\ClassRoom;
use App\Models\Holiday;
use App\Models\HolidayClass;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Rules\StudentPaymentSettled;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    /**
     * Rentang kalender: kelas adalah slot mingguan tanpa akhir, jadi kejadiannya
     * harus dibatasi. Sedikit ke belakang agar riwayat terdekat tetap terlihat.
     */
    private const CALENDAR_PAST_DAYS = 45;

    private const CALENDAR_FUTURE_DAYS = 120;

    /**
     * Status slot untuk filter panel → warna badge availability().
     *
     * Dicocokkan lewat warnanya, bukan lewat aturan tersendiri, supaya filter
     * tidak pernah berbeda pendapat dengan badge yang tampil di barisnya —
     * keduanya bersumber dari satu pemanggilan availability() yang sama.
     */
    private const SLOT_STATUS_COLORS = [
        'tersedia' => 'success',
        'penuh' => 'danger',
        'tanpa-tutor' => 'warning',
        'lewat' => 'dark',
        'ditutup' => 'secondary',
    ];

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();

        // Panel yang terbuka. Halaman ini menampung tiga pekerjaan terpisah —
        // memproses request, mengatur slot, dan menandai kalender — jadi hanya
        // satu yang ditampilkan sekaligus. Disimpan di query string supaya
        // bertahan setelah filter disubmit atau form penanda kalender redirect.
        $tab = $request->string('tab')->toString();
        $tab = in_array($tab, ['slots', 'markers'], true) ? $tab : 'requests';

        $requests = ReplacementRequest::query()
            ->with(['student.payments', 'classRoom', 'originClass', 'approver'])
            // Request lama tetap terlihat walau muridnya kini menunggak; yang
            // dicegah adalah pengajuan baru (lihat validateReplacement).
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('request_status', $status))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('classRoom', fn ($c) => $c->where('class_category', 'like', "%{$search}%"))
                    ->orWhereHas('originClass', fn ($c) => $c->where('class_category', 'like', "%{$search}%"));
            }))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Daftar slot kelas + ketersediaan, untuk panel tutup/buka slot di layar Scheduler.
        // Diurutkan di PHP: hari kini diturunkan dari schedule_date, jadi tidak
        // bisa dipakai di ORDER BY.
        $allSlots = ClassRoom::query()
            ->with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->get()
            ->sortBy(fn (ClassRoom $c) => [$c->day_of_week, $c->timeLabel()])
            ->values();

        // Filter panel slot. Disaring di PHP, bukan lewat WHERE: koleksinya memang
        // sudah dimuat penuh untuk menghitung ringkasan, dan statusnya berasal
        // dari availability() yang tak punya padanan langsung di SQL.
        $slotSearch = $request->string('slot_search')->toString();
        $slotStatus = $request->string('slot_status')->toString();

        $slots = $allSlots
            ->when($slotSearch !== '', fn ($c) => $c->filter(
                fn (ClassRoom $s) => str_contains(mb_strtolower($s->class_category.' '.$s->class_code), mb_strtolower($slotSearch))
            ))
            ->when(isset(self::SLOT_STATUS_COLORS[$slotStatus]), fn ($c) => $c->filter(
                fn (ClassRoom $s) => $s->availability()['color'] === self::SLOT_STATUS_COLORS[$slotStatus]
            ))
            ->values();

        // Hari libur untuk panel pengelolaan (yang akan datang di atas).
        $holidays = Holiday::orderBy('date')->get();

        // Acara / agenda umum untuk panel pengelolaan.
        $calendarEvents = CalendarEvent::orderBy('date')->orderBy('start_time')->get();

        // Ringkasan untuk scorecard di atas halaman — dihitung dari seluruh slot,
        // bukan dari hasil filter panel: ini gambaran keadaan, bukan cerminan
        // pencarian yang sedang dilakukan admin.
        $pendingCount = ReplacementRequest::where('request_status', 'pending')->count();
        $totalSlots = $allSlots->count();
        $availableSlots = $allSlots->filter->isAvailable()->count();

        // Request pending milik murid yang menunggak — perlu ditinjau admin
        // sebelum di-approve.
        $arrearsCount = ReplacementRequest::where('request_status', 'pending')
            ->whereHas('student', fn ($s) => $s->inArrears())
            ->count();

        return view('schedules.index', compact(
            'requests', 'status', 'search', 'slots', 'holidays', 'calendarEvents',
            'pendingCount', 'availableSlots', 'totalSlots', 'arrearsCount', 'tab',
            'slotSearch', 'slotStatus'
        ));
    }

    /**
     * Tambah acara / agenda umum ke kalender.
     */
    public function storeEvent(Request $request)
    {
        $data = $request->validateWithBag('event', [
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        DB::transaction(function () use ($data) {
            $event = CalendarEvent::create($data);
            ActivityLog::record('created', $event, "Menambah acara \"{$event->title}\"");
        });

        return back()->with('success', 'Acara berhasil ditambahkan ke kalender.');
    }

    /**
     * Hapus acara / agenda.
     */
    public function destroyEvent(CalendarEvent $event)
    {
        DB::transaction(function () use ($event) {
            ActivityLog::record('deleted', $event, "Menghapus acara \"{$event->title}\"");
            $event->delete();
        });

        return back()->with('success', 'Acara berhasil dihapus.');
    }

    /**
     * Tambah tanggal libur / kelas ditiadakan.
     */
    public function storeHoliday(Request $request)
    {
        $data = $request->validateWithBag('holiday', [
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['nullable', 'string', 'max:255'],
        ], [
            'date.unique' => 'Tanggal tersebut sudah terdaftar sebagai hari libur.',
        ]);

        DB::transaction(function () use ($data) {
            $holiday = Holiday::create($data);
            ActivityLog::record('created', $holiday, 'Menambah hari libur '.$holiday->date->format('d M Y'));
        });
        ClassRoom::flushHolidayCache();

        return back()->with('success', 'Hari libur berhasil ditambahkan.');
    }

    /**
     * Hapus tanggal libur.
     */
    public function destroyHoliday(Holiday $holiday)
    {
        DB::transaction(function () use ($holiday) {
            ActivityLog::record('deleted', $holiday, 'Menghapus hari libur '.$holiday->date->format('d M Y'));
            $holiday->delete();
        });
        ClassRoom::flushHolidayCache();

        return back()->with('success', 'Hari libur berhasil dihapus.');
    }

    /**
     * Kalender gabungan: jadwal kelas reguler + replacement class.
     */
    public function calendar()
    {
        $events = [];

        // Jadwal kelas reguler. Setiap slot mingguan direntangkan jadi satu event
        // per kejadian dalam rentang tampilan — hari libur otomatis dilewati oleh
        // occurrencesBetween(). Available = biru; penuh/ditutup/lewat = abu-abu.
        $classes = ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->get();

        $from = Carbon::today()->subDays(self::CALENDAR_PAST_DAYS);
        $to = Carbon::today()->addDays(self::CALENDAR_FUTURE_DAYS);

        foreach ($classes as $class) {
            $slotAvailable = $class->isAvailable();
            $av = $class->availability();

            foreach ($class->occurrencesBetween($from, $to) as $at) {
                // Sesi yang sudah lewat tidak bisa dipakai sebagai slot pengganti,
                // walau slot mingguannya sendiri masih available.
                $past = $at->isPast();
                $available = $slotAvailable && ! $past;
                $label = $past ? 'Sudah lewat' : $av['text'];

                $events[] = [
                    'title' => $class->class_category.($available ? '' : ' ('.$label.')'),
                    'start' => $at->format('Y-m-d\TH:i:s'),
                    'color' => $available ? '#0EA5E9' : '#94A3B8',
                    'extendedProps' => [
                        'type' => 'Kelas Reguler',
                        'tutor' => $class->tutor->name ?? '-',
                        'category' => ucfirst($class->class_category),
                        'cat' => $class->class_category, // nilai mentah untuk pencocokan level murid
                        'classId' => $class->id,
                        'code' => $class->class_code,
                        'schedule' => $class->scheduleLabel(),
                        'availability' => $label,
                        'available' => $available,
                        'past' => $past,
                        'occupancy' => $class->enrolledCount().' / '.$class->capacity,
                    ],
                ];
            }
        }

        // Holiday Class — sesi musiman saat libur sekolah. Fuchsia, satu-satunya
        // warna yang belum dipakai: biru kelas reguler, abu penuh/ditutup, amber
        // & hijau & merah replacement, oranye hari libur, indigo acara.
        //
        // Bukan slot kelas pengganti (sekali sesi & berbayar), jadi sengaja tanpa
        // extendedProps 'available' — mode "cari kelas pengganti" hanya
        // menampilkannya sebagai konteks, tidak bisa diajukan.
        foreach (HolidayClass::orderBy('schedule')->get() as $session) {
            $events[] = [
                'title' => '🌞 '.$session->class_name,
                'start' => $session->schedule->format('Y-m-d\TH:i:s'),
                'color' => '#C026D3',
                'url' => route('holiday-classes.edit', $session),
                'extendedProps' => [
                    'type' => 'Holiday Class',
                    'linkLabel' => 'Kelola Holiday Class',
                    'occupancy' => $session->capacity.' kursi ditawarkan',
                    'note' => 'Biaya Rp '.number_format((float) $session->price, 0, ',', '.').' / peserta',
                    // hasPassed(), bukan schedule->isPast(): batasnya awal hari, sama
                    // seperti scopeUpcoming() yang dipakai website & badge di menu
                    // Holiday Class. Sesi yang sedang berlangsung harus tetap tampil
                    // sampai harinya berakhir, bukan hilang begitu jam mulai terlewat.
                    'past' => $session->hasPassed(),
                ],
            ];
        }

        // Replacement class (warna sesuai status).
        $statusColors = ['pending' => '#F59E0B', 'approved' => '#10B981', 'rejected' => '#EF4444'];
        $replacements = ReplacementRequest::with(['student', 'classRoom', 'originClass'])->get();

        foreach ($replacements as $req) {
            $events[] = [
                'title' => 'Replacement: '.($req->student->name ?? '-'),
                'start' => $this->combineDateTime($req->replacement_date, $req->replacement_time),
                'color' => $statusColors[$req->request_status] ?? '#6B7280',
                'url' => route('schedules.edit', $req),
                'extendedProps' => [
                    'type' => 'Replacement Class',
                    'status' => ucfirst($req->request_status),
                    'originClass' => $req->originClass->class_category ?? '-',
                    'newClass' => $req->classRoom->class_category ?? '-',
                    'reason' => $req->reason ?: '-',
                    // Disembunyikan toggle "Hanya slot available" — lihat visibleEvents().
                    'past' => $req->isPast(),
                ],
            ];
        }

        // Hari libur: tampil sebagai event seharian, berdiri sendiri walau tak ada jadwal kelas.
        // Dua representasi: tint latar seharian + chip berlabel (agar jelas & muncul di tampilan daftar).
        foreach (Holiday::orderBy('date')->get() as $holiday) {
            $date = $holiday->date->format('Y-m-d');
            $props = [
                'type' => 'Hari Libur',
                'reason' => $holiday->name ?: 'Kelas ditiadakan',
                'holiday' => true,
            ];
            // Oranye — sengaja dibedakan dari biru kelas reguler & amber replacement pending.
            // Tint latar seharian (FullCalendar merender background event dgn opacity rendah).
            $events[] = [
                'start' => $date,
                'allDay' => true,
                'display' => 'background',
                'color' => '#FB923C',
                'extendedProps' => $props,
            ];
            // Chip berlabel (bisa diklik, tampil juga di listMonth).
            $events[] = [
                'title' => '🏖️ Libur'.($holiday->name ? ': '.$holiday->name : ''),
                'start' => $date,
                'allDay' => true,
                'color' => '#EA580C',
                'extendedProps' => $props,
            ];
        }

        // Acara / agenda umum. Jam kosong = seharian.
        foreach (CalendarEvent::orderBy('date')->get() as $ev) {
            $allDay = $ev->isAllDay();
            $events[] = [
                'title' => $ev->title,
                'start' => $allDay ? $ev->date->format('Y-m-d') : $this->combineDateTime($ev->date, $ev->start_time),
                'end' => (! $allDay && $ev->end_time) ? $this->combineDateTime($ev->date, $ev->end_time) : null,
                'allDay' => $allDay,
                'color' => $ev->color ?: '#6366F1',
                'extendedProps' => [
                    'type' => 'Acara',
                    'note' => $ev->description ?: '-',
                ],
            ];
        }

        // Murid yang masih ikut kelas & tidak menunggak, untuk mode "Cari kelas
        // pengganti" (filter slot per level murid).
        $students = Student::attendable()
            ->settled()
            ->orderBy('name')
            ->get(['id', 'name', 'student_id', 'class_type']);

        return view('schedules.calendar', ['events' => $events, 'students' => $students]);
    }

    /**
     * Gabungkan tanggal (Carbon) + jam (string HH:MM:SS) jadi ISO string.
     */
    private function combineDateTime($date, ?string $time): string
    {
        $iso = $date->format('Y-m-d');

        return $time ? $iso.'T'.substr($time, 0, 8) : $iso;
    }

    public function create()
    {
        // Kelas pengganti adalah fasilitas tambahan, jadi ditahan selama menunggak.
        $students = $this->studentsWithActiveClass(Student::attendable()->settled());
        $classes = ClassRoom::with('tutor')->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_category')->get();

        return view('schedules.create', compact('students', 'classes'));
    }

    /**
     * Murid + kelas aktifnya, dipakai form untuk mengisi otomatis "kelas asal".
     */
    private function studentsWithActiveClass($query)
    {
        return $query->with(['classes' => fn ($q) => $q->wherePivot('status', 'active')])
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $this->validateReplacement($request);

        // Status default Pending untuk request Admin.
        $data['request_status'] = 'pending';

        DB::transaction(function () use ($data) {
            $replacement = ReplacementRequest::create($data);
            ActivityLog::record('created', $replacement, 'Mengajukan replacement class');
        });

        return redirect()->route('schedules.index')->with('success', 'Request replacement class berhasil diajukan (status Pending).');
    }

    public function edit(ReplacementRequest $schedule)
    {
        // Murid tanpa tunggakan + murid yang sudah terlanjur dipilih di request ini.
        $students = $this->studentsWithActiveClass(
            Student::query()->where(fn ($q) => $q->settled()->orWhere('id', $schedule->student_id))
        );
        $classes = ClassRoom::with('tutor')->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_category')->get();

        return view('schedules.edit', ['request' => $schedule, 'students' => $students, 'classes' => $classes]);
    }

    public function update(Request $request, ReplacementRequest $schedule)
    {
        $data = $this->validateReplacement($request, $schedule);

        DB::transaction(function () use ($schedule, $data) {
            $schedule->update($data);
            ActivityLog::record('updated', $schedule, 'Memperbarui replacement class');
        });

        return redirect()->route('schedules.index')->with('success', 'Request replacement class berhasil diperbarui.');
    }

    public function updateStatus(Request $request, ReplacementRequest $schedule)
    {
        $data = $request->validate([
            'request_status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        DB::transaction(function () use ($schedule, $data) {
            $schedule->update([
                'request_status' => $data['request_status'],
                'approved_by' => auth()->id(),
            ]);
            ActivityLog::record('updated', $schedule, "Replacement class {$data['request_status']}");
        });

        return back()->with('success', "Request berhasil di-{$data['request_status']}.");
    }

    public function destroy(ReplacementRequest $schedule)
    {
        DB::transaction(function () use ($schedule) {
            ActivityLog::record('deleted', $schedule, 'Menghapus replacement class');
            $schedule->delete();
        });

        return redirect()->route('schedules.index')->with('success', 'Request replacement class berhasil dihapus.');
    }

    /**
     * Validasi request replacement.
     *
     * Slot tujuan harus AVAILABLE (tidak penuh, tidak ditutup, tutornya ada).
     * Saat mengedit, kelas yang sudah dipilih sebelumnya tetap diizinkan meski
     * kini penuh/ditutup. Tipe kelas boleh berbeda — murid diizinkan pindah
     * lintas tipe.
     *
     * Kelas asal boleh sama dengan kelas baru: kelas adalah slot mingguan, jadi
     * "pindah ke kelas yang sama pada sesi lain" adalah kasus yang wajar. Tapi
     * sesi penggantinya harus benar-benar berbeda — lihat
     * assertReplacementDiffersFromOrigin().
     *
     * "Sudah lewat" dan "hari libur" dinilai pada `replacement_date`, bukan pada
     * slotnya — slot mingguan sendiri tidak pernah kedaluwarsa.
     */
    private function validateReplacement(Request $request, ?ReplacementRequest $current = null): array
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id', new StudentPaymentSettled],
            // Kelas asal: jadwal yang ditinggalkan. Opsional karena data lama belum punya.
            'origin_class_id' => ['nullable', 'exists:classes,id'],
            // Tanggal sesi yang dilewatkan. Boleh kosong — diisi dari sesi terdekat
            // kelas asal (lihat applyMissedDate). Boleh lampau: kelas pengganti
            // sering diminta setelah anaknya absen.
            //
            // Harus sesi yang benar-benar ada di kelas asal. Dropdown di form sudah
            // membatasinya, tapi itu penjaga sisi browser saja — tanggal lain tak
            // pernah cocok dengan absensi, jadi muridnya tidak akan dikeluarkan dari
            // sesi mana pun dan kolomnya terisi tanpa berfungsi.
            'missed_date' => [
                'bail', 'nullable', 'date',
                function ($attribute, $value, $fail) use ($request) {
                    $origin = ClassRoom::find($request->input('origin_class_id'));

                    if (! $origin || $origin->occursOn(Carbon::parse($value))) {
                        return;
                    }

                    $fail("Tanggal tersebut bukan sesi kelas \"{$origin->class_category}\" ({$origin->scheduleLabel()}) — bisa jadi jatuh di hari lain atau pada hari libur. Pilih salah satu sesi kelas tersebut.");
                },
            ],
            'class_id' => [
                'required',
                'exists:classes,id',
                function ($attribute, $value, $fail) use ($current) {
                    // Biarkan kelas yang sama saat edit walau statusnya kini berubah.
                    if ($current && (int) $value === (int) $current->class_id) {
                        return;
                    }

                    $class = ClassRoom::with('tutor')->find($value);
                    if (! $class) {
                        return;
                    }

                    if (! $class->isAvailable()) {
                        // Alasan spesifik sesuai kondisi slot.
                        $reason = match (true) {
                            $class->isClosed() => 'sudah ditutup admin',
                            ! $class->hasTutor() => 'belum ada tutor',
                            $class->isFull() => 'sudah penuh',
                            default => 'tidak punya sesi mendatang',
                        };
                        $fail("Kelas \"{$class->class_category}\" {$reason} sehingga tidak bisa dijadikan slot pengganti. Silakan pilih slot lain yang tersedia.");
                    }

                    // Catatan: beda tipe kelas TIDAK ditolak — murid boleh pindah lintas
                    // tipe. Ketidakcocokan hanya ditandai di UI sebagai peringatan.
                },
            ],
            // Boleh kosong: yang kosong diisi dari sesi berikutnya kelas tujuan
            // (lihat applyClassSchedule). Yang diisi admin dipakai apa adanya.
            'replacement_date' => [
                // bail: closure di bawah memanggil Carbon::parse, jadi jangan sampai
                // ikut jalan saat formatnya sendiri sudah tidak valid.
                'bail', 'nullable', 'date',
                // Sesi pengganti yang sudah lewat tidak ada gunanya diajukan. Saat
                // mengedit, tanggal lama tetap diizinkan agar request lama bisa dirapikan.
                function ($attribute, $value, $fail) use ($current) {
                    $date = Carbon::parse($value)->startOfDay();

                    if ($date->lt(Carbon::today()) && (! $current || $current->replacement_date->toDateString() !== $date->toDateString())) {
                        $fail('Tanggal pengganti sudah lewat. Pilih tanggal hari ini atau setelahnya.');

                        return;
                    }

                    if (in_array($date->toDateString(), ClassRoom::holidayDates(), true)) {
                        $fail('Tanggal pengganti jatuh pada hari libur — kelas ditiadakan pada tanggal tersebut.');
                    }
                },
            ],
            'replacement_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'reason' => ['nullable', 'string'],
        ], [
            // Pesan Indonesia agar admin langsung paham apa yang kurang.
            'student_id.required' => 'Murid belum dipilih.',
            'class_id.required' => 'Kelas tujuan belum dipilih. Jika daftar kelas kosong, berarti tidak ada slot yang tersedia saat ini.',
            'replacement_date.date' => 'Tanggal pengganti tidak valid.',
            'replacement_time.date_format' => 'Jam pengganti tidak valid (format JJ:MM).',
        ]);

        // Urutannya penting: isian yang dikosongkan diisi dulu dari jadwal kelas
        // tujuan, baru diperiksa. Untuk kelas yang sama, isian otomatis itu justru
        // selalu jatuh tepat di jadwal kelas asal — persis yang harus ditolak.
        $data = $this->applyMissedDate($data);
        $data = $this->applyClassSchedule($data);
        $this->assertReplacementDiffersFromOrigin($data);

        return $data;
    }

    /**
     * Lengkapi tanggal sesi yang dilewatkan dengan sesi terdekat kelas asal.
     *
     * Itu tebakan yang paling masuk akal saat admin tidak mengisinya sendiri,
     * dan sekali tersimpan nilainya tidak ikut bergeser walau waktu berjalan —
     * itulah sebabnya kolomnya ada.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyMissedDate(array $data): array
    {
        if (! empty($data['missed_date']) || empty($data['origin_class_id'])) {
            return $data;
        }

        $data['missed_date'] = ClassRoom::find($data['origin_class_id'])
            ?->nextOccurrence()?->format('Y-m-d');

        return $data;
    }

    /**
     * Sesi pengganti tidak boleh jatuh tepat pada sesi yang ditinggalkan.
     *
     * Kelas asal boleh sama dengan kelas baru, termasuk pada hari & jam yang sama:
     * "menyusul di sesi minggu berikutnya, kelas yang sama" justru pola replacement
     * yang paling wajar. Yang tidak masuk akal hanyalah menaruh sesi pengganti
     * persis di sesi yang sedang dilewatkan — di situ tidak ada yang berpindah.
     *
     * Karena itu yang dibandingkan tanggal persis, bukan hari mingguannya, dan
     * acuannya `missed_date` yang tersimpan — bukan lagi kejadian terdekat kelas
     * asal yang jawabannya berubah seiring waktu berjalan.
     *
     * Kelas tujuan yang berbeda tidak dibatasi — bentrok jam antar kelas justru
     * wajar, karena murid memang meninggalkan sesi aslinya.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertReplacementDiffersFromOrigin(array $data): void
    {
        $originId = $data['origin_class_id'] ?? null;
        $missed = $data['missed_date'] ?? null;

        if (! $originId || ! $missed || (int) $originId !== (int) ($data['class_id'] ?? 0)) {
            return;
        }

        $origin = ClassRoom::find($originId);

        if (! $origin) {
            return;
        }

        $missedAt = Carbon::parse($missed);
        $sameDate = Carbon::parse($data['replacement_date'])->toDateString() === $missedAt->toDateString();
        $sameTime = substr((string) $data['replacement_time'], 0, 5) === $origin->timeLabel();

        if ($sameDate && $sameTime) {
            $label = $missedAt->locale('id')->translatedFormat('l, j F Y').' - '.$origin->timeLabel();

            throw ValidationException::withMessages([
                'replacement_date' => "Sesi pengganti jatuh tepat pada sesi yang ditinggalkan ({$label}), jadi tidak ada sesi yang berpindah. Pilih tanggal sesi lain, atau kelas tujuan yang berbeda.",
            ]);
        }
    }

    /**
     * Lengkapi tanggal/jam pengganti yang dikosongkan dengan jadwal kelas tujuan.
     *
     * Satu baris `classes` adalah satu sesi bertanggal, jadi jadwal kelas tujuan
     * adalah default yang benar. Isian admin sengaja tidak dipaksa sama — sesi
     * pengganti kadang digeser dari jadwal aslinya.
     */
    private function applyClassSchedule(array $data): array
    {
        $class = ClassRoom::find($data['class_id'] ?? null);
        if (! $class) {
            return $data;
        }

        // Sesi mingguan berikutnya yang nyata, bukan tanggal jadwal apa pun yang
        // tersimpan di kelas — kelas hanya menyimpan pola hari + jam.
        $next = $class->nextOccurrence();

        // Replacement di kelas yang sama: sesi terdekat justru sesi yang sedang
        // ditinggalkan, jadi default-nya digeser ke sesi sesudahnya. Tanpa ini
        // isian otomatis selalu jatuh tepat di sesi yang ditolak
        // assertReplacementDiffersFromOrigin().
        if ($next && (int) ($data['origin_class_id'] ?? 0) === (int) $class->id) {
            $next = $class->nextOccurrence($next->copy()->addSecond()) ?? $next;
        }

        $data['replacement_date'] ??= ($next ?? Carbon::today())->format('Y-m-d');
        $data['replacement_time'] ??= $class->timeLabel();

        return $data;
    }
}
