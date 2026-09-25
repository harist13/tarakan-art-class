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
        $type = (string) request()->query('tipe');

        return view('public.contact', [
            'programOptions' => $this->programOptions(),
            'hours' => config('site.hours', []),
            'faq' => config('site.faq', []),
            // Pra-pilih program & tipenya bila datang dari tombol "Daftar kelas ini".
            'selectedProgram' => $this->resolveSelectedProgram(request()->query('kelas')),
            'selectedType' => array_key_exists($type, Lead::classTypeOptions()) ? $type : null,
            'ageSuggestions' => $this->ageSuggestions(),
        ]);
    }

    /**
     * Rentang usia tiap program beserta slug-nya, supaya dropdown "Program"
     * terisi otomatis begitu orang tua mengisi tanggal lahir. Holiday Class
     * tidak ikut: sesi liburan terbuka untuk segala usia.
     *
     * @return list<array{min: int, value: string}> urut menaik menurut usia minimum
     */
    private function ageSuggestions(): array
    {
        return collect(config('site.programs', []))
            ->reject(fn (array $program) => $this->isHolidayProgram($program))
            ->map(function (array $program) {
                // Cukup usia minimumnya: "3 – 5 tahun" → 3, "7 tahun ke atas" → 7,
                // "2,5 – 3 tahun" → 2 (bulat ke bawah, usia di form juga bulat).
                return preg_match('/\d+/', (string) ($program['age'] ?? ''), $angka)
                    ? ['min' => (int) $angka[0], 'value' => $program['slug']]
                    : null;
            })
            ->filter()
            ->sortBy('min')
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
     * Kartu program untuk halaman depan & halaman Program: empat program tetap
     * di config, dengan angka live dari slot kelas yang sedang dibuka.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function programsWithLiveData(): Collection
    {
        return $this->programsFromClasses($this->openClasses());
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
     * Susun kartu keempat program di config dari slot kelas di tabel `classes`.
     *
     * Satu kartu mewakili satu program, yang bisa mencakup beberapa kategori
     * kelas (mis. Sketching = Basic Sketch + Basic Perspective + Character) —
     * lihat `categories` di config/site.php. Angka yang berbeda antarslot
     * dirangkum jadi rentang ("6 – 10 anak per kelas") alih-alih diam-diam
     * memilih salah satu.
     *
     * Yang datang dari database: durasi, kapasitas, biaya, dan jadwal. Program
     * yang belum punya slot dibuka tetap tampil dengan angka cadangan dari config.
     *
     * @param  Collection<int, ClassRoom>  $classes  slot yang sedang dibuka
     * @return Collection<int, array<string, mixed>>
     */
    private function programsFromClasses(Collection $classes): Collection
    {
        return collect(config('site.programs', []))
            ->map(function (array $program) use ($classes) {
                if ($this->isHolidayProgram($program)) {
                    return $this->withHolidaySession($program, HolidayClass::upcoming()->first());
                }

                $group = $classes->filter(fn (ClassRoom $class) => $this->belongsToProgram($class->class_category, $program));

                if ($group->isEmpty()) {
                    return $this->withFixedLabels($program) + [
                        'schedule' => $program['schedule_hint'] ?? null,
                        'is_live' => false,
                        'next_class' => null,
                        'next_holiday' => null,
                    ];
                }

                // Kelas trial dijual per kedatangan, jadi tarifnya tidak boleh
                // ikut terhitung sebagai iuran bulanan. Jadwal & kapasitasnya
                // pun sekali jalan — kalau ada slot reguler, itu yang mewakili.
                // Tarif visit sendiri tetap per program (`visit_price` di config),
                // tidak dibaca dari kelas trial.
                $regular = $group->reject->isTrial();
                $main = $regular->isNotEmpty() ? $regular : $group;

                return $this->withFixedLabels(array_merge($program, [
                    'duration' => $this->durationLabel($main),
                    'capacity' => $this->capacityLabel($main),
                    'price' => $this->feeLabel($regular, '/ bulan'),
                    'schedule_hint' => $this->compactScheduleLabel($main),
                    'schedule' => $this->weeklyScheduleLabel($main),
                    'is_live' => true,
                    // Slot mingguan tidak punya "tanggal jadwal" tunggal, jadi yang
                    // terdekat dihitung dari sesi berikutnya masing-masing slot.
                    'next_class' => $main->sortBy(fn (ClassRoom $c) => $c->nextOccurrence()?->timestamp ?? PHP_INT_MAX)->first(),
                    'next_holiday' => null,
                ]));
            })
            ->values();
    }

    /**
     * Teks kapasitas & jadwal yang ditetapkan manual di config (`capacity_label`,
     * `schedule_label`) menang atas rangkuman dari slot. Dipakai saat rangkuman
     * slot tidak mewakili cara program itu dijual, mis. "1 tutor, maks. 3–4 anak".
     *
     * @param  array<string, mixed>  $program
     * @return array<string, mixed>
     */
    private function withFixedLabels(array $program): array
    {
        if (filled($program['capacity_label'] ?? null)) {
            $program['capacity'] = $program['capacity_label'];
        }

        if (filled($program['schedule_label'] ?? null)) {
            $program['schedule_hint'] = $program['schedule_label'];
            $program['schedule'] = $program['schedule_label'];
        }

        return $program;
    }

    /**
     * Apakah sebuah kategori kelas termasuk program ini.
     *
     * `classes.class_category` diketik bebas, jadi "Pre-school", "pre school",
     * dan "Preschool" harus sama-sama cocok — perbandingan memakai huruf & angka
     * saja, huruf kecil.
     *
     * @param  array<string, mixed>  $program
     */
    private function belongsToProgram(?string $category, array $program): bool
    {
        if (blank($category)) {
            return false;
        }

        $keys = array_map(fn (string $c) => $this->copyKey($c), $program['categories'] ?? []);

        return in_array($this->copyKey($category), $keys, true);
    }

    /** Kunci pencocokan kategori: huruf & angka saja, huruf kecil. */
    private function copyKey(string $category): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($category))) ?: mb_strtolower(trim($category));
    }

    /** @param  array<string, mixed>  $program */
    private function isHolidayProgram(array $program): bool
    {
        return ($program['source'] ?? null) === 'holiday_classes';
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
            return $program + [
                'schedule' => $program['schedule_hint'] ?? null,
                'is_live' => false,
                'next_class' => null,
                'next_holiday' => null,
            ];
        }

        return array_merge($program, [
            'schedule_hint' => $this->formatSession($session->schedule),
            'capacity' => $session->capacity.' anak per sesi',
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
     * Baris tabel "Jadwal umum per program" pada halaman Jadwal: empat program
     * yang sama dengan kartu program, dengan hari + jam dari slot yang benar-benar
     * ada di Class Management. Program tanpa slot ditandai `is_live` false supaya
     * tabelnya mengaku "perkiraan".
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
        return $this->programsFromClasses($classes->where('status', 'open'))
            ->map(function (array $program) use ($holidayClasses) {
                if (! $this->isHolidayProgram($program)) {
                    return $program;
                }

                $sessions = $holidayClasses
                    ->map(fn (HolidayClass $session) => $this->formatSession($session->schedule))
                    ->implode(' · ');

                return array_merge($program, [
                    'schedule' => $sessions ?: $program['schedule_hint'],
                    'is_live' => $sessions !== '',
                ]);
            })
            ->values();
    }

    /**
     * Pilihan "Program" pada form kontak. Holiday Class tidak ikut — pendaftarannya
     * lewat chat WhatsApp admin, lihat Lead::programOptions().
     *
     * @return array<string, string> slug => label
     */
    private function programOptions(): array
    {
        return Lead::programOptions();
    }

    /**
     * Terjemahkan `?kelas=` menjadi slug program. Tombol "Daftar kelas ini"
     * mengirim slug; tautan lama yang masih mengirim nama kategori kelas
     * ("Basic Mewarnai") dipetakan lewat `categories` di config.
     */
    private function resolveSelectedProgram(?string $wanted): ?string
    {
        if (blank($wanted)) {
            return null;
        }

        $program = collect(config('site.programs', []))
            ->filter(fn (array $program) => array_key_exists($program['slug'], Lead::programOptions()))
            ->first(fn (array $program) => $program['slug'] === $wanted || $this->belongsToProgram($wanted, $program));

        return $program['slug'] ?? null;
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
            $lead->programName() ? 'Program: '.$lead->programName() : null,
            $lead->classTypeName() ? 'Tipe kelas: '.$lead->classTypeName() : null,
            $lead->address ? 'Alamat: '.$lead->address : null,
            $lead->message ? 'Pesan: '.$lead->message : null,
        ]);

        $text = 'Halo '.config('site.name').", saya ingin mendaftarkan anak saya.\n\n".implode("\n", $lines);

        return 'https://wa.me/'.config('site.contact.whatsapp').'?text='.rawurlencode($text);
    }
}
