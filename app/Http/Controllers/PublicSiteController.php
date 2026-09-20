<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Mail\NewLeadNotification;
use App\Models\Artwork;
use App\Models\ClassRoom;
use App\Models\HolidayClass;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Website publik (marketing & informasi) — terpisah dari sistem admin.
 *
 * Konten statis diambil dari config/site.php; data jadwal & ketersediaan
 * kursi ditarik live dari modul Class Management (F3).
 */
class PublicSiteController extends Controller
{
    /** Berapa hari ke depan yang ditampilkan pada halaman Jadwal. */
    private const SCHEDULE_HORIZON_DAYS = 21;

    /** Berapa sesi Holiday Class mendatang yang diumumkan sekaligus. */
    private const HOLIDAY_SESSION_LIMIT = 4;

    /** Berapa karya yang diintip di blok galeri halaman depan. */
    private const GALLERY_PREVIEW_LIMIT = 6;

    /** Berapa karya per halaman pada galeri lengkap. */
    private const GALLERY_PER_PAGE = 24;

    /**
     * Nama hari & bulan dalam bahasa Indonesia — tidak bergantung pada locale
     * Carbon supaya tampilan sistem admin (locale bawaan) tidak ikut berubah.
     */
    private const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /** Dipakai di kartu program, yang kolom jadwalnya sempit. */
    private const SHORT_DAY_NAMES = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    private const MONTH_NAMES = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function home()
    {
        $today = Carbon::today();

        return view('public.home', [
            'programs' => $this->programsWithLiveData(),
            'testimonials' => config('site.testimonials', []),
            'galleryPreview' => $this->galleryItems(limit: self::GALLERY_PREVIEW_LIMIT),
            'stats' => config('site.about.stats', []),
            'holidayClasses' => HolidayClass::upcoming()->limit(4)->get(),
        ]);
    }

    public function about()
    {
        return view('public.about', [
            'about' => config('site.about', []),
        ]);
    }

    public function programs()
    {
        return view('public.programs', [
            'programs' => $this->programsWithLiveData(),
        ]);
    }

    public function gallery(Request $request)
    {
        // Kategori dihitung dari seluruh arsip (bukan halaman yang sedang dibuka)
        // supaya tombol filter tidak hilang-timbul saat pengunjung berpindah halaman.
        $used = $this->galleryCategorySlugs();

        $categories = collect(config('site.gallery_categories', []))
            ->filter(fn ($label, $slug) => $used->contains($slug));

        $active = $request->query('kategori');

        if ($active && ! $categories->has($active)) {
            $active = null;
        }

        return view('public.gallery', [
            'items' => $this->paginateItems($this->galleryItems($active), $request),
            'categories' => $categories,
            'active' => $active,
        ]);
    }

    public function schedule()
    {
        $today = Carbon::today();
        $until = $today->copy()->addDays(self::SCHEDULE_HORIZON_DAYS);

        // Slot mingguan diambil seluruhnya, lalu direntangkan jadi sesi konkret
        // dalam rentang tampilan — bukan disaring per tanggal, karena satu slot
        // berjalan berulang tanpa tanggal akhir.
        $classes = ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('schedule_time')
            ->get();

        // Sesi liburan sengaja tidak dibatasi rentang tampilan seperti kelas reguler:
        // sifatnya pengumuman, jadi justru berguna diketahui jauh-jauh hari.
        $holidayClasses = HolidayClass::upcoming()->limit(self::HOLIDAY_SESSION_LIMIT)->get();

        return view('public.schedule', [
            'programs' => $this->programsWithWeeklySchedule($classes, $holidayClasses),
            'days' => $this->sessionsByDate($classes, $today, $until),
            'holidayClasses' => $holidayClasses,
            'until' => $until,
        ]);
    }

    public function contact()
    {
        $classOptions = $this->classOptions();
        $wanted = request()->query('kelas');
        $selected = $this->resolveSelectedClass($classOptions, $wanted);

        return view('public.contact', [
            'classOptions' => $classOptions,
            'hours' => config('site.hours', []),
            'faq' => config('site.faq', []),
            // Pra-pilih kelas & tipenya bila datang dari tombol "Daftar kelas ini".
            'selected' => $selected,
            'selectedType' => $this->resolveSelectedType($classOptions, $wanted, $selected),
            'ageSuggestions' => $this->ageSuggestions(),
        ]);
    }

    /**
     * Rentang usia tiap kategori kelas beserta nilainya untuk dropdown "Tipe
     * kelas", supaya pilihannya terisi otomatis begitu orang tua mengisi tanggal
     * lahir.
     *
     * Kategori diambil dari tabel `classes` (nilainya harus sama persis dengan
     * isi dropdown), sedangkan batas usianya dari keterangan statis di config —
     * tidak ada kolom usia di tabel `classes`. Kategori yang belum ditulis
     * keterangannya tidak ikut disarankan: usia bawaan berlaku untuk semua
     * kategori, jadi menyarankannya sama saja dengan menebak. Begitu pula
     * kategori ber-`suggest_by_age` false, yang penentunya bukan umur.
     *
     * @return list<array{max: int, value: string}> urut menaik menurut usia minimum
     */
    private function ageSuggestions(): array
    {
        $copy = collect(config('site.program_copy', []))
            ->keyBy(fn (array $text, string $category) => $this->copyKey($category));

        $ranges = ClassRoom::query()
            ->distinct()
            ->pluck('class_category')
            ->filter(fn (?string $category) => filled($category))
            ->unique(fn (string $category) => $this->copyKey($category))
            ->map(function (string $category) use ($copy) {
                $text = $copy[$this->copyKey($category)] ?? [];

                return ($text['suggest_by_age'] ?? true) === false
                    ? null
                    : ['age' => $text['age'] ?? null, 'value' => $category];
            })
            ->filter();

        // Database masih kosong: brosur di config yang jadi acuannya. Program
        // tanpa kategori (Holiday Class) tidak ikut — sesi liburan terbuka untuk
        // segala usia.
        if ($ranges->isEmpty()) {
            $ranges = collect(config('site.programs', []))
                ->filter(fn (array $program) => filled($program['category'] ?? null))
                ->map(fn (array $program) => [
                    'age' => $program['age'] ?? null,
                    'value' => $program['category'],
                ]);
        }

        return $ranges
            ->map(function (array $range) {
                // "3 – 5 tahun" → [3, 5]. Kategori dengan satu angka saja dianggap
                // batas bawah sekaligus batas atasnya.
                preg_match_all('/\d+/', (string) $range['age'], $angka);
                $bounds = array_map('intval', $angka[0]);

                return $bounds === [] ? null : [
                    'min' => $bounds[0],
                    'max' => end($bounds),
                    'value' => $range['value'],
                ];
            })
            ->filter()
            ->sortBy('min')
            ->map(fn (array $range) => ['max' => $range['max'], 'value' => $range['value']])
            ->values()
            ->all();
    }

    /**
     * Simpan lead + kabari admin. Sesuai PRD ini bukan pendaftaran self-service:
     * data hanya diteruskan ke admin untuk ditindaklanjuti.
     */
    public function storeLead(StoreLeadRequest $request)
    {
        $lead = Lead::create($request->validated());

        $to = config('site.lead_notification_email') ?: config('site.contact.email');

        // Kegagalan kirim email tidak boleh membuang data calon murid —
        // lead sudah tersimpan, admin masih bisa melihatnya di database.
        try {
            Mail::to($to)->send(new NewLeadNotification($lead));
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim notifikasi lead', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }

        return redirect()
            ->route('public.contact')
            ->with('lead_sent', $lead->child_name)
            ->with('lead_whatsapp', $this->whatsappLink($lead));
    }

    public function sitemap()
    {
        // Halaman publik beserta berkas Blade-nya. lastmod diambil dari waktu
        // ubah berkas itu, bukan tanggal hari ini: peta situs yang mengaku
        // "berubah hari ini" setiap hari akan diabaikan Google. Jadwal dikecualikan
        // karena isinya memang bergerak sendiri tiap minggu tanpa berkas diubah.
        $pages = [
            'public.home' => ['view' => 'home', 'priority' => '1.0', 'changefreq' => 'weekly'],
            'public.programs' => ['view' => 'programs', 'priority' => '0.9', 'changefreq' => 'monthly'],
            'public.schedule' => ['view' => 'schedule', 'priority' => '0.9', 'changefreq' => 'weekly'],
            'public.about' => ['view' => 'about', 'priority' => '0.7', 'changefreq' => 'monthly'],
            'public.gallery' => ['view' => 'gallery', 'priority' => '0.7', 'changefreq' => 'weekly'],
            'public.contact' => ['view' => 'contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ];

        $urls = collect($pages)->map(function (array $page, string $name) {
            $file = resource_path('views/public/'.$page['view'].'.blade.php');

            return [
                'loc' => route($name),
                'priority' => $page['priority'],
                'changefreq' => $page['changefreq'],
                'lastmod' => is_file($file)
                    ? Carbon::createFromTimestamp(filemtime($file))->toDateString()
                    : Carbon::today()->toDateString(),
            ];
        })->values();

        return response()
            ->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    // ─── Helper ────────────────────────────────────────────────────────

    /**
     * Rentangkan slot mingguan jadi sesi konkret, dikelompokkan per tanggal.
     *
     * @param  Collection<int, ClassRoom>  $classes
     * @return Collection<string, Collection<int, ClassRoom>>
     */
    private function sessionsByDate(Collection $classes, Carbon $from, Carbon $to): Collection
    {
        $byDate = [];

        foreach ($classes as $class) {
            foreach ($class->occurrencesBetween($from, $to) as $at) {
                $byDate[$at->toDateString()][] = $class;
            }
        }

        ksort($byDate);

        return collect($byDate)->map(
            fn (array $slots) => collect($slots)->sortBy(fn (ClassRoom $c) => $c->timeLabel())->values()
        );
    }

    /**
     * Kartu program untuk halaman depan & halaman Program.
     *
     * Isinya kategori kelas yang benar-benar ada di Class Management, ditutup
     * kartu Holiday Class dari modulnya sendiri. Brosur di config baru dipakai
     * bila tabel `classes` masih kosong — lihat programsFromClasses().
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function programsWithLiveData(): Collection
    {
        $programs = $this->programsFromClasses($this->openClasses());

        if ($programs->isEmpty()) {
            $programs = $this->fallbackPrograms();
        }

        return $programs->concat($this->holidayPrograms())->values();
    }

    /**
     * Slot kelas yang sedang dibuka, lengkap dengan jumlah murid aktifnya.
     *
     * @return Collection<int, ClassRoom>
     */
    private function openClasses(): Collection
    {
        return ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->where('status', 'open')
            ->get();
    }

    /**
     * Susun kartu program dari kategori kelas di tabel `classes`.
     *
     * Satu kartu mewakili satu kategori, bukan satu baris kelas: sanggar bisa
     * punya belasan slot "Basic Mewarnai" pada hari & jam berbeda, dan yang
     * dicari orang tua adalah kelasnya, bukan daftar slotnya. Karena itu angka
     * yang berbeda antarslot dirangkum jadi rentang ("6 – 10 anak per kelas")
     * alih-alih diam-diam memilih salah satu.
     *
     * Yang datang dari database: durasi, kapasitas, biaya, jadwal, dan tipe
     * kelas. Yang tetap dari config: usia, warna, ikon, ringkasan, poin materi —
     * tidak ada kolomnya di tabel `classes`, dan memang kalimat brosur.
     *
     * Kategori diperbandingkan tanpa peduli ejaan ("Pre-school" = "preschool")
     * karena `class_category` diketik bebas oleh admin; ejaan yang ditampilkan
     * tetap ejaan admin.
     *
     * @param  Collection<int, ClassRoom>  $classes  slot yang sedang dibuka
     * @return Collection<int, array<string, mixed>>
     */
    private function programsFromClasses(Collection $classes): Collection
    {
        $copy = collect(config('site.program_copy', []))
            ->keyBy(fn (array $text, string $category) => $this->copyKey($category));

        // Kategori yang keterangannya sudah ditulis tampil lebih dulu, dengan
        // urutan seperti di config; sisanya menyusul menurut abjad.
        $order = $copy->keys()->flip();

        return $classes
            ->filter(fn (ClassRoom $class) => filled($class->class_category))
            ->groupBy(fn (ClassRoom $class) => $this->copyKey($class->class_category))
            ->sortBy(fn (Collection $group, string $key) => sprintf('%03d-%s', $order[$key] ?? 999, $key))
            ->map(function (Collection $group, string $key) use ($copy) {
                // Kelas trial dijual per kedatangan, jadi tarifnya tidak boleh
                // ikut terhitung sebagai iuran bulanan. Jadwal & kapasitasnya
                // pun sekali jalan — kalau ada slot reguler, itu yang mewakili.
                $regular = $group->reject->isTrial();
                $trial = $group->filter->isTrial();
                $main = $regular->isNotEmpty() ? $regular : $group;

                $name = trim((string) $group->first()->class_category);
                $text = ($copy[$key] ?? []) + config('site.program_default', []);

                return $text + [
                    // Untuk anchor "#basic-mewarnai" di halaman Program.
                    'slug' => Str::slug($name) ?: $key,
                    // Nilai yang dikirim ke form kontak — sama persis dengan isi
                    // dropdown "Kelas yang diminati", yang juga dari kolom ini.
                    'category' => $name,
                    'name' => $name,
                    'duration' => $this->durationLabel($main),
                    'capacity' => $this->capacityLabel($main),
                    'price' => $this->feeLabel($regular, '/ bulan'),
                    'visit_price' => $this->feeLabel($trial, '/ visit'),
                    'schedule_hint' => $this->compactScheduleLabel($main),
                    'schedule' => $this->weeklyScheduleLabel($main),
                    'is_live' => true,
                    // Slot mingguan tidak punya "tanggal jadwal" tunggal, jadi yang
                    // terdekat dihitung dari sesi berikutnya masing-masing slot.
                    'next_class' => $main->sortBy(fn (ClassRoom $c) => $c->nextOccurrence()?->timestamp ?? PHP_INT_MAX)->first(),
                    'next_holiday' => null,
                ];
            })
            ->values();
    }

    /**
     * Kunci pencocokan keterangan statis: huruf & angka saja, huruf kecil.
     *
     * `classes.class_category` diketik bebas, jadi "Pre-school", "pre school",
     * dan "Preschool" harus mengenai entri config yang sama — kalau tidak,
     * kartunya diam-diam jatuh ke keterangan umum hanya karena satu tanda hubung.
     */
    private function copyKey(string $category): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($category))) ?: mb_strtolower(trim($category));
    }

    /**
     * Brosur cadangan dari config, tanpa Holiday Class (diurus terpisah).
     *
     * Dipakai saat belum ada satu pun kelas di database — instalasi baru tidak
     * seharusnya menampilkan halaman Program yang kosong melompong.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fallbackPrograms(): Collection
    {
        return collect(config('site.programs', []))
            ->reject(fn (array $program) => ($program['source'] ?? null) === 'holiday_classes')
            ->map(fn (array $program) => $program + [
                'schedule' => $program['schedule_hint'] ?? null,
                'is_live' => false,
                'next_class' => null,
                'next_holiday' => null,
            ])
            ->values();
    }

    /**
     * Kartu Holiday Class — nol atau satu, tergantung ada tidaknya program
     * semacam itu di config.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function holidayPrograms(): Collection
    {
        $program = $this->holidayProgram();

        return $program
            ? collect([$this->withHolidaySession($program, HolidayClass::upcoming()->first())])
            : collect();
    }

    /** "90 menit / pertemuan", atau rentangnya bila antarslot berbeda-beda. */
    private function durationLabel(Collection $slots): string
    {
        $menit = $slots->map(fn (ClassRoom $class) => $class->durationMinutes())
            ->filter()->unique()->sort()->values();

        return match (true) {
            // Slot lama yang jam selesainya belum diisi: lebih baik mengaku
            // tidak tahu daripada mengarang durasi.
            $menit->isEmpty() => 'Tanyakan admin',
            $menit->count() === 1 => $menit->first().' menit / pertemuan',
            default => $menit->first().' – '.$menit->last().' menit / pertemuan',
        };
    }

    /** "10 anak per kelas", atau rentangnya bila antarslot berbeda-beda. */
    private function capacityLabel(Collection $slots): string
    {
        $kursi = $slots->map(fn (ClassRoom $class) => (int) $class->capacity)
            ->filter()->unique()->sort()->values();

        return match (true) {
            $kursi->isEmpty() => 'Tanyakan admin',
            $kursi->count() === 1 => $kursi->first().' anak per kelas',
            default => $kursi->first().' – '.$kursi->last().' anak per kelas',
        };
    }

    /**
     * "Rp360.000 / bulan", atau "Mulai Rp300.000 / bulan" bila tarif antarslot
     * berbeda. Null bila tidak ada slotnya sama sekali — kartu lalu menyebut
     * paket itu belum tersedia, bukan menampilkan harga Rp0.
     */
    private function feeLabel(Collection $slots, string $satuan): ?string
    {
        $tarif = $slots->map(fn (ClassRoom $class) => (float) $class->class_fee)
            ->unique()->sort()->values();

        if ($tarif->isEmpty()) {
            return null;
        }

        $rupiah = fn (float $nilai) => 'Rp'.number_format($nilai, 0, ',', '.');

        return ($tarif->count() === 1 ? $rupiah($tarif->first()) : 'Mulai '.$rupiah($tarif->first()))
            .' '.$satuan;
    }

    /**
     * Jadwal padat untuk kartu program: "Sel, Kam, Sab · 13 slot".
     *
     * Kategori populer bisa punya belasan slot; menuliskan semua hari + jamnya
     * di kartu justru membuat baris "Jadwal" tak terbaca. Rincian per jam ada
     * di halaman Jadwal — lihat weeklyScheduleLabel().
     */
    private function compactScheduleLabel(Collection $slots): string
    {
        $hari = $slots->map(fn (ClassRoom $class) => (int) $class->day_of_week)
            ->unique()->sort()->values();

        if ($hari->isEmpty()) {
            return 'Tanyakan admin';
        }

        $label = $hari->map(fn (int $dow) => self::SHORT_DAY_NAMES[$dow])->implode(', ');
        $jam = $slots->map(fn (ClassRoom $class) => $class->timeLabel())->unique();

        return $jam->count() === 1
            ? $label.', '.str_replace(':', '.', $jam->first()).' WITA'
            : $label.' · '.$slots->count().' slot';
    }

    /**
     * Jadwal lengkap untuk tabel halaman Jadwal: hari-hari yang berbagi jam
     * yang sama digabung — "Selasa & Kamis, 15.00 WITA · Sabtu, 09.30 WITA".
     */
    private function weeklyScheduleLabel(Collection $slots): string
    {
        return $slots
            ->groupBy(fn (ClassRoom $class) => $class->timeLabel())
            ->sortKeys()
            ->map(function (Collection $group, string $jam) {
                $hari = $group
                    ->map(fn (ClassRoom $class) => (int) $class->day_of_week)
                    ->unique()->sort()
                    ->map(fn (int $dow) => self::DAY_NAMES[$dow])
                    ->implode(' & ');

                return $hari.', '.str_replace(':', '.', $jam).' WITA';
            })
            ->implode(' · ');
    }

    /**
     * Kartu "Holiday Class" tidak punya kategori di tabel `classes`, jadi jadwal,
     * kapasitas, dan biayanya diambil dari sesi terdekat di modul Holiday Class.
     * Selama admin belum menjadwalkan sesi, nilai perkiraan di config yang dipakai —
     * pola yang sama dengan `schedule_hint` pada halaman Jadwal.
     *
     * @param  array<string, mixed>  $program
     * @return array<string, mixed>
     */
    private function withHolidaySession(array $program, ?HolidayClass $session): array
    {
        if (! $session) {
            return $program + ['next_class' => null, 'next_holiday' => null];
        }

        return array_merge($program, [
            'schedule_hint' => $this->formatSession($session->schedule),
            'capacity' => $session->capacity.' anak per sesi',
            'price' => 'Rp'.number_format((float) $session->price, 0, ',', '.').' / sesi',
            'next_class' => null,
            'next_holiday' => $session,
        ]);
    }

    /** mis. "Sabtu, 5 Jul 2026, 09.00 WITA" */
    private function formatSession(Carbon $when): string
    {
        return self::DAY_NAMES[(int) $when->dayOfWeek].', '
            .$this->formatSessionShort($when)
            .', '.$when->format('H.i').' WITA';
    }

    /** mis. "5 Jul 2026" */
    private function formatSessionShort(Carbon $when): string
    {
        return $when->day.' '.self::MONTH_NAMES[(int) $when->month].' '.$when->year;
    }

    /**
     * Baris tabel "Jadwal umum per program" pada halaman Jadwal.
     *
     * Program & jadwalnya sama-sama disusun dari slot yang benar-benar ada di
     * Class Management: satu baris per kategori kelas, dengan hari + jam yang
     * berulang dikelompokkan jadi satu kalimat. Saat database masih kosong,
     * yang tampil adalah brosur cadangan di config, ditandai `is_live` false
     * supaya tabelnya mengaku "perkiraan".
     *
     * Holiday Class tidak berulang mingguan, jadi barisnya diisi tanggal sesi
     * mendatang dari modul Holiday Class, bukan pola hari + jam.
     *
     * @param  Collection<int, ClassRoom>  $classes  seluruh slot mingguan (sudah di-fetch)
     * @param  Collection<int, HolidayClass>  $holidayClasses  sesi liburan mendatang
     * @return Collection<int, array<string, mixed>>
     */
    private function programsWithWeeklySchedule(Collection $classes, Collection $holidayClasses): Collection
    {
        // Kelas yang ditutup tidak ikut diiklankan sebagai jadwal rutin.
        $programs = $this->programsFromClasses($classes->where('status', 'open'));

        if ($programs->isEmpty()) {
            $programs = $this->fallbackPrograms();
        }

        $holiday = $this->holidayProgram();

        if ($holiday) {
            $sessions = $holidayClasses
                ->map(fn (HolidayClass $session) => $this->formatSession($session->schedule))
                ->implode(' · ');

            $programs = $programs->push($holiday + [
                'schedule' => $sessions ?: $holiday['schedule_hint'],
                'is_live' => $sessions !== '',
            ]);
        }

        return $programs->values();
    }

    /**
     * Pilihan "Kelas yang diminati" pada form kontak, diambil live dari tabel
     * `classes` supaya dropdown ikut berubah begitu admin menambah kelas baru.
     * Bila database masih kosong (mis. instalasi baru), jatuh kembali ke daftar
     * program di config agar form tetap bisa dipakai.
     *
     * @return Collection<int, array{value: string, label: string, category: ?string}>
     */
    private function classOptions(): Collection
    {
        $fromDb = ClassRoom::query()
            ->select('class_category')
            ->distinct()
            ->orderBy('class_category')
            ->get()
            ->map(fn (ClassRoom $class) => [
                'value' => $class->class_category,
                'label' => $class->class_category,
                'category' => $class->class_category,
            ]);

        if ($fromDb->isNotEmpty()) {
            return $fromDb->concat($this->holidayClassOption())->values();
        }

        // Instalasi baru tanpa kelas sama sekali: seluruh program di config
        // ditawarkan sebagai brosur, termasuk Holiday Class walau belum ada sesi.
        return collect(config('site.programs', []))->map(fn (array $program) => [
            'value' => $program['slug'],
            'label' => $program['name'].' ('.$program['age'].')',
            'category' => $this->programCategory($program),
        ]);
    }

    /**
     * Opsi "Holiday Class" pada dropdown kelas. Hanya ditawarkan bila ada sesi
     * mendatang: beda dari kelas reguler yang jadwalnya berulang, sesi liburan
     * yang sudah lewat tidak bisa diikuti lagi. Label menyebut tema & tanggalnya
     * supaya orang tua tahu persis yang didaftarkan.
     *
     * @return Collection<int, array{value: string, label: string, category: ?string}>
     */
    private function holidayClassOption(): Collection
    {
        $program = $this->holidayProgram();
        $session = $program ? HolidayClass::upcoming()->first() : null;

        if (! $program || ! $session) {
            return collect();
        }

        return collect([[
            'value' => $program['slug'],
            'label' => $program['name'].' — '.$session->class_name
                .' ('.$this->formatSessionShort($session->schedule).')',
            'category' => $this->programCategory($program),
        ]]);
    }

    /**
     * Program di config yang datanya berasal dari modul Holiday Class.
     *
     * @return array<string, mixed>|null
     */
    private function holidayProgram(): ?array
    {
        return collect(config('site.programs', []))
            ->first(fn (array $program) => ($program['source'] ?? null) === 'holiday_classes');
    }

    /**
     * Kategori sebuah program untuk keperluan penyaringan di form kontak.
     *
     * Holiday Class tidak punya kategori di tabel `classes`, jadi dipetakan ke
     * tipe kelas khusus milik Lead — dengan begitu dropdown "Tipe kelas" dan
     * "Kelas yang diminati" memakai nilai yang sama.
     *
     * @param  array<string, mixed>  $program
     */
    private function programCategory(array $program): ?string
    {
        return ($program['source'] ?? null) === 'holiday_classes'
            ? Lead::HOLIDAY_TYPE
            : $program['category'];
    }

    /**
     * Terjemahkan `?kelas=` menjadi salah satu value dropdown. Tombol "Daftar
     * kelas ini" mengirim slug program, sedangkan dropdown berisi nama kelas
     * dari database — keduanya dijembatani lewat kategori kelas.
     *
     * @param  Collection<int, array{value: string, label: string, category: ?string}>  $options
     */
    private function resolveSelectedClass(Collection $options, ?string $wanted): ?string
    {
        if (! $wanted) {
            return null;
        }

        if ($options->contains(fn (array $option) => $option['value'] === $wanted)) {
            return $wanted;
        }

        return $options->firstWhere('category', $wanted)['value'] ?? null;
    }

    /**
     * Tipe kelas yang dipra-pilih pada form kontak.
     *
     * Diambil dari opsi kelas yang cocok bila ada. Kalau program yang diklik
     * belum punya jadwal (kelas reguler belum dibuka, atau sesi liburan belum
     * dijadwalkan), tipenya tetap dipra-pilih dari config supaya niat orang tua
     * tidak hilang — form lalu menampilkan petunjuk "belum ada jadwal" alih-alih
     * dua dropdown kosong.
     *
     * @param  Collection<int, array{value: string, label: string, category: ?string}>  $options
     */
    private function resolveSelectedType(Collection $options, ?string $wanted, ?string $selected): ?string
    {
        $matched = $selected ? $options->firstWhere('value', $selected) : null;

        if ($matched && $matched['category']) {
            return $matched['category'];
        }

        $program = collect(config('site.programs', []))->firstWhere('slug', $wanted);

        return $program ? $this->programCategory($program) : null;
    }

    /**
     * Item galeri publik: foto karya murid dari modul Galeri Karya (terbaru dulu),
     * disusul foto statis yang didaftarkan manual di config/site.php.
     *
     * Config tetap dipakai karena tidak semua foto punya pemiliknya di tabel
     * `artworks` — dokumentasi kegiatan & pameran ("kegiatan") tidak diunggah
     * lewat modul karya murid.
     *
     * @param  string|null  $category  slug kategori; null = semua
     * @param  int|null  $limit  batas jumlah item (untuk preview halaman depan)
     * @return Collection<int, array<string, mixed>>
     */
    private function galleryItems(?string $category = null, ?int $limit = null): Collection
    {
        $items = $this->artworkItems($category, $limit)
            ->concat($this->configGalleryItems($category));

        return ($limit ? $items->take($limit) : $items)->values();
    }

    /**
     * Foto karya murid sebagai item galeri publik.
     *
     * Kategori diambil dari `class_type` murid — nilainya sama persis dengan slug
     * program di config (preschool/coloring/drawing), jadi filter kategori pada
     * halaman galeri langsung berlaku tanpa tabel pemetaan tambahan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function artworkItems(?string $category = null, ?int $limit = null): Collection
    {
        return Artwork::query()
            ->with('student')
            ->when($category, fn ($q, $slug) => $q->whereHas('student', fn ($s) => $s->where('class_type', $slug)))
            ->orderByDesc('taken_on')
            ->orderByDesc('id')
            ->when($limit, fn ($q, $n) => $q->limit($n))
            ->get()
            // File bisa saja hilang dari disk (mis. storage belum di-link atau
            // dibersihkan manual) — jangan sampai grid menampilkan gambar rusak.
            ->filter(fn (Artwork $artwork) => Storage::disk('public')->exists($artwork->photo_path))
            ->map(fn (Artwork $artwork) => [
                'category' => $artwork->student?->class_type,
                'url' => $artwork->photoUrl(),
            ])
            ->values();
    }

    /**
     * Foto statis dari config, disaring ke file yang benar-benar ada agar
     * grid tidak menampilkan gambar rusak.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function configGalleryItems(?string $category = null): Collection
    {
        return collect(config('site.gallery', []))
            ->when($category, fn ($items, $slug) => $items->where('category', $slug))
            ->filter(fn (array $item) => is_file(public_path('images/gallery/'.$item['file'])))
            ->map(fn (array $item) => $item + [
                'url' => asset('images/gallery/'.$item['file']),
            ])
            ->values();
    }

    /**
     * Slug kategori yang benar-benar punya foto — dipakai untuk menampilkan
     * tombol filter seperlunya saja.
     *
     * @return Collection<int, string>
     */
    private function galleryCategorySlugs(): Collection
    {
        $fromArtworks = Artwork::query()
            ->join('students', 'students.id', '=', 'artworks.student_id')
            ->distinct()
            ->pluck('students.class_type');

        return $fromArtworks
            ->concat($this->configGalleryItems()->pluck('category'))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Bagi item galeri menjadi halaman.
     *
     * Pemenggalannya dilakukan setelah koleksi tersusun, bukan lewat LIMIT di
     * database, karena satu halaman bisa memuat dua sumber sekaligus (karya murid
     * + foto statis config). Yang dimuat hanya baris metadata, jadi ongkosnya
     * masih ringan untuk ukuran arsip studio.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return LengthAwarePaginator<array<string, mixed>>
     */
    private function paginateItems(Collection $items, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, self::GALLERY_PER_PAGE)->values(),
            $items->count(),
            self::GALLERY_PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * Tautan WhatsApp ke studio berisi seluruh isian form — dipakai pada layar
     * "terima kasih" sebagai cadangan bila tab WhatsApp yang dibuka saat submit
     * diblokir browser (atau JavaScript dimatikan). Formatnya sengaja dibuat
     * sama dengan yang disusun di contact.blade.php.
     */
    private function whatsappLink(Lead $lead): string
    {
        $lines = array_filter([
            'Nama anak: '.$lead->child_name,
            $lead->date_of_birth ? 'Tanggal lahir: '.$lead->date_of_birth->format('d/m/Y') : null,
            $lead->child_age ? 'Usia: '.$lead->child_age.' tahun' : null,
            'Nama orang tua / wali: '.$lead->parent_name,
            'Nomor WhatsApp: '.$lead->parent_phone,
            $lead->parent_email ? 'Email: '.$lead->parent_email : null,
            $lead->classTypeName() ? 'Tipe kelas: '.$lead->classTypeName() : null,
            $lead->programName() ? 'Kelas yang diminati: '.$lead->programName() : null,
            $lead->address ? 'Alamat: '.$lead->address : null,
            $lead->message ? 'Pesan: '.$lead->message : null,
        ]);

        $text = 'Halo '.config('site.name').", saya ingin mendaftarkan anak saya.\n\n".implode("\n", $lines);

        return 'https://wa.me/'.config('site.contact.whatsapp').'?text='.rawurlencode($text);
    }
}
