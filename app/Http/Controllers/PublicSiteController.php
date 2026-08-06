<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Mail\NewLeadNotification;
use App\Models\CalendarEvent;
use App\Models\ClassRoom;
use App\Models\Holiday;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    public function home()
    {
        return view('public.home', [
            'programs' => $this->programsWithLiveData()->take(4),
            'testimonials' => config('site.testimonials', []),
            'galleryPreview' => $this->galleryItems()->take(6),
            'stats' => config('site.about.stats', []),
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
            'faq' => config('site.faq', []),
        ]);
    }

    public function gallery(Request $request)
    {
        $items = $this->galleryItems();
        $active = $request->query('kategori');

        // Hanya kategori yang benar-benar punya karya yang ditawarkan sebagai filter.
        $categories = collect(config('site.gallery_categories', []))
            ->filter(fn ($label, $slug) => $items->contains(fn ($i) => $i['category'] === $slug));

        if ($active && ! $categories->has($active)) {
            $active = null;
        }

        return view('public.gallery', [
            'items' => $active ? $items->where('category', $active)->values() : $items,
            'categories' => $categories,
            'active' => $active,
        ]);
    }

    public function schedule()
    {
        $today = Carbon::today();
        $until = $today->copy()->addDays(self::SCHEDULE_HORIZON_DAYS);

        $classes = ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->whereBetween('schedule_date', [$today, $until])
            ->orderBy('schedule_date')
            ->orderBy('schedule_time')
            ->get();

        return view('public.schedule', [
            'programs' => $this->programsWithWeeklySchedule($classes),
            'days' => $classes->groupBy(fn (ClassRoom $c) => $c->schedule_date->toDateString()),
            'holidays' => Holiday::whereBetween('date', [$today, $until])->orderBy('date')->get(),
            'announcements' => CalendarEvent::where('date', '>=', $today)->orderBy('date')->limit(6)->get(),
            'until' => $until,
        ]);
    }

    public function contact()
    {
        $classOptions = $this->classOptions();
        $selected = $this->resolveSelectedClass($classOptions, request()->query('kelas'));

        return view('public.contact', [
            'classOptions' => $classOptions,
            'hours' => config('site.hours', []),
            // Pra-pilih kelas & tipenya bila datang dari tombol "Daftar kelas ini".
            'selected' => $selected,
            'selectedType' => $classOptions->firstWhere('value', $selected)['category'] ?? null,
        ]);
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
     * Program dari config, dilengkapi jadwal terdekat & sisa kursi dari database
     * bila kategorinya ada di Class Management.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function programsWithLiveData(): Collection
    {
        $programs = collect(config('site.programs', []));

        $categories = $programs->pluck('category')->filter()->unique()->all();

        if ($categories === []) {
            return $programs->map(fn (array $p) => $p + ['next_class' => null]);
        }

        // Satu query untuk semua kategori: slot terdekat yang masih dibuka.
        $upcoming = ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->whereIn('class_category', $categories)
            ->where('status', 'open')
            ->whereDate('schedule_date', '>=', Carbon::today())
            ->orderBy('schedule_date')
            ->orderBy('schedule_time')
            ->get()
            ->groupBy('class_category');

        return $programs->map(function (array $program) use ($upcoming) {
            $next = $program['category']
                ? ($upcoming[$program['category']] ?? collect())->first()
                : null;

            return $program + ['next_class' => $next];
        });
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
     * @param  Collection<int, ClassRoom>  $classes  slot pada rentang tampilan (sudah di-fetch)
     * @return Collection<int, array<string, mixed>>
     */
    private function programsWithWeeklySchedule(Collection $classes): Collection
    {
        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        // Kelas yang ditutup tidak ikut diiklankan sebagai jadwal rutin.
        $byCategory = $classes->where('status', 'open')->groupBy('class_category');

        return collect(config('site.programs', []))->map(function (array $program) use ($byCategory, $dayNames) {
            $slots = $program['category'] ? ($byCategory[$program['category']] ?? collect()) : collect();

            // Dikelompokkan per jam supaya hasilnya sepadat config: "Selasa & Kamis, 15.00 WITA".
            $schedule = $slots
                ->groupBy(fn (ClassRoom $c) => substr($c->schedule_time, 0, 5))
                ->sortKeys()
                ->map(function (Collection $group, string $time) use ($dayNames) {
                    $days = $group
                        ->map(fn (ClassRoom $c) => (int) $c->schedule_date->dayOfWeek)
                        ->unique()->sort()
                        ->map(fn (int $dow) => $dayNames[$dow])
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
            ->select('class_name', 'class_category')
            ->distinct()
            ->orderBy('class_name')
            ->get()
            ->map(fn (ClassRoom $class) => [
                'value' => $class->class_name,
                'label' => $class->class_name,
                'category' => $class->class_category,
            ]);

        if ($fromDb->isNotEmpty()) {
            return $fromDb->values();
        }

        return collect(config('site.programs', []))->map(fn (array $program) => [
            'value' => $program['slug'],
            'label' => $program['name'].' ('.$program['age'].')',
            'category' => $program['category'],
        ]);
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
     * Item galeri dari config, disaring ke file yang benar-benar ada agar
     * grid tidak menampilkan gambar rusak.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function galleryItems(): Collection
    {
        return collect(config('site.gallery', []))
            ->filter(fn (array $item) => is_file(public_path('images/gallery/'.$item['file'])))
            ->map(fn (array $item) => $item + [
                'url' => asset('images/gallery/'.$item['file']),
                'caption' => $item['caption'] ?? '',
            ])
            ->values();
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
