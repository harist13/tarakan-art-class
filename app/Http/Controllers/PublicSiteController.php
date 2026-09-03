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
     * Rentang usia tiap program beserta tipe kelas yang disarankan, untuk mengisi
     * otomatis dropdown "Tipe kelas" begitu orang tua mengisi tanggal lahir.
     *
     * Diturunkan dari `age` di config supaya saran usianya selalu sama dengan yang
     * tertulis di brosur program — dulu batasnya ditulis ulang di JavaScript dan
     * sempat melenceng dari config. Program tanpa kategori (mis. Holiday Class) tidak
     * ikut disarankan: sesi liburan terbuka untuk segala usia.
     *
     * @return list<array{max: int, value: string}> urut menaik menurut usia minimum
     */
    private function ageSuggestions(): array
    {
        return collect(config('site.programs', []))
            ->filter(fn (array $program) => filled($program['category'] ?? null))
            ->map(function (array $program) {
                // "3 – 5 tahun" → [3, 5]. Program dengan satu angka saja dianggap
                // batas bawah sekaligus batas atasnya.
                preg_match_all('/\d+/', (string) ($program['age'] ?? ''), $angka);
                $bounds = array_map('intval', $angka[0]);

                return $bounds === [] ? null : [
                    'min' => $bounds[0],
                    'max' => end($bounds),
                    'value' => $program['category'],
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
        $urls = collect(['public.home', 'public.about', 'public.programs', 'public.gallery', 'public.schedule', 'public.contact'])
            ->map(fn (string $name) => [
                'loc' => route($name),
                'priority' => $name === 'public.home' ? '1.0' : '0.8',
            ]);

        return response()
            ->view('public.sitemap', ['urls' => $urls, 'lastmod' => Carbon::today()->toDateString()])
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
     * Program dari config, dilengkapi jadwal terdekat & sisa kursi dari database
     * bila kategorinya ada di Class Management.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function programsWithLiveData(): Collection
    {
        $programs = collect(config('site.programs', []));

        $categories = $programs->pluck('category')->filter()->unique()->all();

        // Satu query untuk semua kategori: slot terdekat yang masih dibuka.
        $upcoming = $categories === [] ? collect() : ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->whereIn('class_category', $categories)
            ->where('status', 'open')
            ->get()
            // Slot mingguan tidak punya "tanggal jadwal" tunggal, jadi urutan
            // terdekat dihitung dari sesi berikutnya masing-masing slot.
            ->sortBy(fn (ClassRoom $c) => $c->nextOccurrence()?->timestamp ?? PHP_INT_MAX)
            ->groupBy('class_category');

        $nextHoliday = $this->usesHolidaySessions($programs)
            ? HolidayClass::upcoming()->first()
            : null;

        return $programs->map(function (array $program) use ($upcoming, $nextHoliday) {
            if (($program['source'] ?? null) === 'holiday_classes') {
                return $this->withHolidaySession($program, $nextHoliday);
            }

            $next = $program['category']
                ? ($upcoming[$program['category']] ?? collect())->first()
                : null;

            return $program + ['next_class' => $next, 'next_holiday' => null];
        });
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

    /**
     * Adakah program yang datanya berasal dari modul Holiday Class? Dipakai supaya
     * query sesi liburan dilewati saat config tidak memuat program semacam itu.
     *
     * @param  Collection<int, array<string, mixed>>  $programs
     */
    private function usesHolidaySessions(Collection $programs): bool
    {
        return $programs->contains(fn (array $program) => ($program['source'] ?? null) === 'holiday_classes');
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
     * Kolom jadwal & durasi disusun dari slot yang benar-benar ada di Class
     * Management, bukan teks statis: hari + jam yang berulang dikelompokkan
     * menjadi satu baris per program. Program yang belum punya slot dalam
     * rentang tampilan jatuh kembali ke `schedule_hint` di config, dan usia
     * tetap dari config karena tidak ada kolomnya di tabel `classes`.
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
        $byCategory = $classes->where('status', 'open')->groupBy('class_category');

        return collect(config('site.programs', []))->map(function (array $program) use ($byCategory, $holidayClasses) {
            if (($program['source'] ?? null) === 'holiday_classes') {
                $sessions = $holidayClasses
                    ->map(fn (HolidayClass $session) => $this->formatSession($session->schedule))
                    ->implode(' · ');

                return $program + [
                    'schedule' => $sessions ?: $program['schedule_hint'],
                    'is_live' => $sessions !== '',
                ];
            }

            $slots = $program['category'] ? ($byCategory[$program['category']] ?? collect()) : collect();

            // Dikelompokkan per jam supaya hasilnya sepadat config: "Selasa & Kamis, 15.00 WITA".
            $schedule = $slots
                ->groupBy(fn (ClassRoom $c) => substr($c->schedule_time, 0, 5))
                ->sortKeys()
                ->map(function (Collection $group, string $time) {
                    $days = $group
                        ->map(fn (ClassRoom $c) => (int) $c->day_of_week)
                        ->unique()->sort()
                        ->map(fn (int $dow) => self::DAY_NAMES[$dow])
                        ->implode(' & ');

                    return $days.', '.str_replace(':', '.', $time).' WITA';
                })
                ->implode(' · ');

            return $program + [
                'schedule' => $schedule ?: $program['schedule_hint'],
                'is_live' => $schedule !== '',
            ];
        });
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
                'caption' => $this->artworkCaption($artwork),
                'url' => $artwork->photoUrl(),
            ])
            ->values();
    }

    /**
     * Keterangan foto di galeri publik.
     *
     * Deskripsi dari admin dipakai apa adanya bila ada. Bila kosong, dipakai nama
     * depan murid saja — cukup untuk membuat karyanya terasa personal tanpa
     * memajang nama lengkap anak di halaman publik.
     */
    private function artworkCaption(Artwork $artwork): string
    {
        if (filled($artwork->description)) {
            return $artwork->description;
        }

        $student = $artwork->student;

        if (! $student) {
            return '';
        }

        $firstName = explode(' ', trim($student->name))[0];

        return 'Karya '.$firstName.($student->age ? ', '.$student->age.' tahun' : '');
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
                'caption' => $item['caption'] ?? '',
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
