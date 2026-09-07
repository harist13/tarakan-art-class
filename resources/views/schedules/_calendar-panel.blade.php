{{-- Panel kalender jadwal: kelas reguler & Holiday Class.

     Replacement tidak digambar sebagai badge sendiri. Murid replacement yang
     sudah disetujui muncul di dalam kelas yang dititipi, dan detail
     pengajuannya dibuka dari baris muridnya.

     Dipakai dua halaman — Kalender jadwal dan tab di Manajemen kelas. Isinya
     dipisah ke sini, bukan disalin, supaya keduanya tidak pernah menampilkan
     jadwal yang berbeda.

     Butuh: $events & $rosters (dari App\Support\ScheduleCalendar) serta $students. --}}
@php
    // Kalender menggambar dua hal yang berbeda, dan keduanya perlu angka sendiri:
    //
    //  - LABEL tiap 1,5 jam (09:00, 10:30, ...) — irama sanggar, yang dicari mata.
    //  - GARIS tiap 30 menit — supaya kelas yang jamnya di luar irama itu, seperti
    //    Preschool 16:00–17:00, tergambar di posisi & tinggi sebenarnya alih-alih
    //    dipaksa masuk petak yang bukan miliknya.
    //
    // Dulu keduanya satu angka, dan akibatnya kelas semacam itu tidak bisa
    // digambar sama sekali.
    $slotMenit = \App\Models\ClassRoom::SLOT_MINUTES;
    $slotDurasi = sprintf('%02d:%02d:00', intdiv($slotMenit, 60), $slotMenit % 60);
@endphp
<style>
    /* Event tampil sebagai label penuh berwarna (background = status) */
    #calendar .fc-event {
        border: none;
        border-radius: 6px;
        padding: 2px 6px;
        margin: 1px 2px;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    }
    #calendar .fc-event .fc-event-main,
    #calendar .fc-event .fc-event-title,
    #calendar .fc-event .fc-event-time {
        color: #fff !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #calendar .fc-event:hover { filter: brightness(0.94); }
    /* Tampilan daftar (listMonth) tetap pakai teks gelap agar terbaca */
    #calendar .fc-list-event .fc-list-event-title a { color: #334155 !important; }

    /* Sel tanggal & petak jam bisa diklik untuk membuka isinya — perlu terasa
       bisa diklik, bukan cuma bisa. */
    #calendar .fc-daygrid-day { cursor: pointer; }
    #calendar .fc-daygrid-day:hover { background-color: var(--surface-2); }
    #calendar .fc-timegrid-col-frame { cursor: pointer; }
    /* Barisnya setengah jam. 1,6rem berarti sesi 60 menit setinggi ~51px, dan itu
       memuat judul 13px dua baris (36px) beserta paddingnya — tapi tidak beserta
       nama tutor. Maka nama tutor yang mengalah, bukan judulnya: lihat batas
       durasi di eventDidMount. Sesi 90 menit (~77px) tetap memuat keduanya. */
    #calendar .fc-timegrid-slot { height: 1.6rem; }

    /* Garis setengah jam ada untuk menempatkan kelas, bukan untuk dibaca.
       Dibuat samar supaya yang terbaca tetap enam pita berlabel. */
    #calendar .fc-timegrid-slot-minor { border-top-style: dotted; opacity: 0.55; }

    /* ── Kepala kolom hari ──
       Bawaannya sebuah <a>, jadi ia mewarisi warna & garis bawah tautan seluruh
       aplikasi dan terbaca seolah bisa diklik ke halaman lain. Di sini ia label,
       bukan tautan. */
    #calendar .fc-col-header-cell { padding: 0.5rem 0.25rem; }
    #calendar .fc-col-header-cell-cushion {
        color: var(--text);
        text-decoration: none;
        display: block;
        width: 100%;
    }
    .cal-dayhead { display: flex; flex-direction: column; line-height: 1.25; }
    .cal-dayhead-name { font-weight: 700; font-size: 0.85rem; }
    .cal-dayhead-date { font-size: 0.72rem; color: var(--text-muted); font-weight: 500; }

    /* Hari ini ditandai satu kolom penuh, bukan cuma kepalanya: itu yang dicari
       mata lebih dulu saat kalender dibuka. */
    #calendar .fc-day-today { background-color: rgba(14,165,233,.05) !important; }
    #calendar .fc-col-header-cell.fc-day-today .cal-dayhead-name { color: var(--primary-dark); }

    /* Jam di kolom kiri adalah tulang punggung tampilan pekan — dibaca lebih
       sering daripada judul kolomnya, jadi tidak boleh sekecil bawaan. */
    #calendar .fc-timegrid-slot-label-cushion {
        font-weight: 700;
        font-size: 0.82rem;
        color: var(--text-muted);
    }
    #calendar .fc-timegrid-axis { width: 4.5rem; }

    /* Satu kelas mengisi penuh petaknya: yang dicari admin di tampilan pekan
       adalah "jam ini terpakai atau tidak", bukan menit persisnya. */
    /* ── Warna di tampilan pekan ──
       Tampilan bulan memberi satu baris tipis per kejadian, jadi bidang warna
       penuh di sana kecil dan justru membantu memindai. Di tampilan pekan
       kejadian yang sama jadi kotak setinggi sesinya — warna pekat seluas itu
       berubah dari penanda jadi bidang yang mendominasi halaman.

       Jadi artinya tetap sama persis (biru kelas tersedia, abu penuh/ditutup,
       fuchsia Holiday Class), hanya kadarnya yang turun: latar seulas tipis dengan
       satu bilah warna penuh di tepi kiri, dan tulisannya memakai warna itu
       sendiri alih-alih putih.

       --ev diisi eventDidMount dari warna eventnya. Kalau color-mix tidak
       didukung browser, seluruh deklarasi ini gugur dan kotaknya kembali ke
       warna pekat bawaan — turun pangkat, bukan rusak. */
    #calendar .fc-timegrid-event {
        padding: 4px 7px;
        box-shadow: none;
        border-radius: 6px;
        /* Dicampur ke warna permukaan, bukan ke transparent: latar tembus membuat
           garis grid & indikator "sekarang" ikut terbaca menembus badge, dan
           pastel yang seharusnya rata jadi belang di tiap garis setengah jam. */
        background-color: color-mix(in srgb, var(--ev, #0EA5E9) 14%, var(--surface, #fff)) !important;
        border-left: 4px solid var(--ev, #0EA5E9) !important;
        color: color-mix(in srgb, var(--ev, #0EA5E9) 70%, #0F172A);
        transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }
    #calendar .fc-timegrid-event .fc-event-main,
    #calendar .fc-timegrid-event .fc-event-title,
    #calendar .fc-timegrid-event .fc-event-time {
        color: inherit !important;
    }
    /* Hover sengaja tidak menyentuh warna latar. Warna di sini punya arti
       (biru kelas tersedia, abu penuh/ditutup); menggesernya saat kursor lewat
       membuat penanda status berkedip jadi status lain. Yang berubah cuma
       kedalaman: bayangan tipis & bilah tepi yang menggelap. */
    #calendar .fc-timegrid-event:hover {
        filter: none;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.13);
        border-left-color: color-mix(in srgb, var(--ev, #0EA5E9) 75%, #000) !important;
    }

    /* Terpilih: badge yang barusan dibuka tetap ditandai, jadi setelah modalnya
       ditutup admin tahu di mana ia tadi berada. */
    #calendar .fc-timegrid-event.is-selected {
        outline: 2px solid var(--ev, #0EA5E9);
        outline-offset: 1px;
    }

    /* Fokus papan ketik. Badge & tautan "+N" bukan tautan ber-href, jadi
       keduanya diberi tabindex di eventDidMount — cincin fokusnya harus ikut
       ada, kalau tidak navigasi Tab berjalan tanpa jejak di layar. */
    #calendar .fc-timegrid-event:focus-visible,
    #calendar .fc-timegrid-more-link:focus-visible {
        outline: 2px solid var(--primary-color, #0EA5E9);
        outline-offset: 2px;
        box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.25);
    }

    /* Latar gelap: ulasannya sedikit lebih tebal & tulisannya dicerahkan, bukan
       digelapkan — rumus terang yang sama akan hilang di sana. */
    [data-bs-theme="dark"] #calendar .fc-timegrid-event {
        background-color: color-mix(in srgb, var(--ev, #0EA5E9) 26%, var(--surface, #0F172A)) !important;
        color: color-mix(in srgb, var(--ev, #0EA5E9) 45%, #F8FAFC);
    }
    [data-bs-theme="dark"] #calendar .fc-timegrid-event:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
        border-left-color: color-mix(in srgb, var(--ev, #0EA5E9) 70%, #FFF) !important;
    }
    #calendar .fc-timegrid-event .fc-event-main,
    #calendar .fc-timegrid-event .fc-event-main-frame {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    /* Bawaan FullCalendar membuat kotak judul memuai memenuhi tinggi blok, dan
       nama tutor yang menyusulnya terdorong ke dasar — dua baris yang sebenarnya
       satu keterangan jadi terbaca sebagai dua hal terpisah dengan lubang di
       tengahnya. Keduanya harus duduk berdampingan di atas. */
    #calendar .fc-timegrid-event .fc-event-title-container { flex-grow: 0; }

    /* Dua jadwal di jam yang sama berbagi lebar kolom, jadi nama kelas harus
       boleh turun baris — tapi hanya di spasi. Tanpa ini "Coloring Class"
       terpenggal jadi "Colorin" + "g Class", dan huruf yang tercecer di awal
       baris lebih mengganggu daripada kata yang terpotong rapi. */
    #calendar .fc-timegrid-event .fc-event-title {
        white-space: normal;
        word-break: normal;
        overflow-wrap: normal;
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1.4;
        text-transform: capitalize;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Tautan "+N": penanda bahwa masih ada jadwal lain di jam itu, jadi ia
       sengaja tidak berwarna seperti event — kalau ikut berwarna, ia terbaca
       sebagai jadwal ketiga alih-alih sebagai pintu ke sisanya.

       Yang harus jelas justru bahwa ia bisa diklik: berlatar, bergaris, kursor
       pointer, dan garisnya menegas saat disinggahi kursor. */
    #calendar .fc-timegrid-more-link {
        background: var(--surface);
        border: 1px dashed var(--border);
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 700;
        border-radius: 5px;
        padding: 2px 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        transition: border-color 0.15s ease, color 0.15s ease, background-color 0.15s ease;
    }
    #calendar .fc-timegrid-more-link:hover {
        border-style: solid;
        border-color: var(--primary-color);
        color: var(--primary-dark);
        background: var(--surface-2);
    }

    /* Daftar yang muncul saat "+N" diklik. */
    #calendar .fc-more-popover { z-index: 1060; }
    #calendar .fc-more-popover .fc-popover-body { min-width: 16rem; padding: 0.5rem; }
    #calendar .fc-more-popover .fc-popover-title { font-weight: 700; }
    #calendar .fc-more-popover .fc-event { margin-bottom: 0.35rem; }

    /* Nama tutor ikut tertulis di petaknya — lihat eventDidMount. Sedikit lebih
       redup dari nama kelas supaya keduanya tidak berebut dibaca duluan, dan
       satu baris ber-ellipsis: "Kak Bagu…" masih terbaca sebagai nama, "Kak B"
       yang terpotong setengah huruf tidak. */
    #calendar .fc-event-tutor {
        margin-top: 2px;
        font-size: 0.75rem;
        font-weight: 400;
        line-height: 1.4;
        opacity: 0.8;
        text-transform: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ── Layar sempit ──
       Kolom hari menyusut jadi sekitar 40px di ponsel; nama tutor di situ tidak
       akan pernah jadi nama, cuma satu-dua huruf lalu ellipsis. Ia disembunyikan
       dan tetap terbaca lewat tooltip badge — lihat atribut title di
       eventDidMount, yang memang memuat tutor & jam lengkap. */
    @media (max-width: 767.98px) {
        #calendar .fc-event-tutor { display: none; }
        #calendar .fc-timegrid-event { padding: 3px 5px; }
        #calendar .fc-timegrid-event .fc-event-title {
            font-size: 0.75rem;
            line-height: 1.35;
        }
    }


    /* ── Keterangan warna ──
       Dulu enam titik kecil berjajar sebagai teks lepas; sekarang tiap warna jadi
       satu pil dengan latar redup sewarna, jadi terbaca sebagai satu kelompok dan
       titiknya tidak lagi harus dicari. */
    .cal-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }
    .cal-legend .legend-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.7rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.4;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .cal-legend .legend-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    /* ── Penelusuran bertingkat ── */
    .drill-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        width: 100%;
        padding: 0.75rem 0.9rem;
        border: 1px solid var(--border);
        border-radius: 0.6rem;
        background: var(--surface);
        color: var(--text);
        text-align: left;
        transition: border-color 0.15s ease, transform 0.15s ease;
    }
    .drill-row:hover {
        border-color: var(--primary-color);
        transform: translateX(2px);
    }
    .drill-time {
        font-weight: 700;
        font-size: 0.95rem;
        white-space: nowrap;
        min-width: 7.5rem;
    }
    .drill-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: var(--text-muted);
    }
</style>
<div class="card">
    <div class="card-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <span class="fw-bold">Jadwal kelas &amp; Replacement Class</span>
            <div class="cal-legend">
                <span class="legend-pill" style="background:rgba(14,165,233,.12); border-color:rgba(14,165,233,.35); color:#0369A1;">
                    <span class="legend-dot" style="background:#0EA5E9;"></span>Kelas tersedia
                </span>
                <span class="legend-pill" style="background:rgba(148,163,184,.16); border-color:rgba(148,163,184,.4); color:#475569;">
                    <span class="legend-dot" style="background:#94A3B8;"></span>Penuh / Ditutup
                </span>
                {{-- Replacement tak lagi berbadge sendiri di kalender: tiga status
                     (menunggu, disetujui, ditolak) berarti tiga warna tambahan yang
                     menumpuk di petak yang sama dengan kelasnya, padahal yang dicari
                     admin selalu "siapa yang hadir di kelas ini hari itu". Murid
                     replacement kini muncul di dalam kelas yang dititipi, dan
                     detailnya dibuka dari sana. Daftar seluruh pengajuan beserta
                     statusnya tetap ada di halaman Scheduler. --}}
                <span class="legend-pill" style="background:rgba(192,38,211,.12); border-color:rgba(192,38,211,.35); color:#A21CAF;">
                    <span class="legend-dot" style="background:#C026D3;"></span>Holiday Class
                </span>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3 small">
            <span class="text-muted"><i class="bi bi-hand-index me-1"></i>Klik petak jam untuk melihat tutor &amp; kelas di jam itu, lalu muridnya.</span>
            <div class="form-check form-switch ms-auto"
                 title="Menyembunyikan kelas yang penuh/ditutup, serta Holiday Class yang jadwalnya sudah lewat.">
                <input class="form-check-input" type="checkbox" id="onlyAvailable" checked>
                <label class="form-check-label" for="onlyAvailable">Hanya slot available</label>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label for="replacementStudent" class="text-nowrap mb-0 fw-semibold"><i class="bi bi-search me-1"></i>Cari kelas pengganti</label>
                <select id="replacementStudent" class="form-select form-select-sm" style="width:200px;">
                    <option value="">— Pilih murid —</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" data-name="{{ $student->name }}" data-category="{{ $student->class_type }}">{{ $student->name }} ({{ $student->class_type }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        {{-- Banner mode "cari kelas pengganti": tampil saat seorang murid dipilih. --}}
        <div id="replacementBanner" class="alert alert-primary d-none align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <span><i class="bi bi-funnel-fill me-1"></i>Menampilkan <strong id="rbCount">0</strong> slot tersedia untuk <strong id="rbName"></strong> (tipe <strong id="rbCategory"></strong>). Slot beda tipe juga bisa dipilih. Klik slot untuk mengajukan replacement.</span>
            <button type="button" id="rbClear" class="btn btn-sm btn-outline-primary"><i class="bi bi-x-lg me-1"></i>Keluar mode</button>
        </div>
        <div id="calendar"></div>
        {{-- Jadwal yang jatuh di luar jam buka tidak punya petak untuk ditempati.
             Disebutkan, bukan dibiarkan hilang tanpa jejak. --}}
        <div id="luarJamBuka" class="alert alert-warning small d-none mt-3 mb-0"></div>
    </div>
</div>

{{-- Satu modal, tiga tingkat: jam → tutor & murid → (data murid, halaman sendiri).
     Tingkatnya cuma div yang bergantian tampil, bukan modal bertumpuk — modal di
     atas modal membuat tombol tutup jadi teka-teki: yang mana yang tertutup. --}}
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none d-none mb-1" id="drillBack">
                        <i class="bi bi-arrow-left me-1"></i><span id="drillBackLabel">Kembali</span>
                    </button>
                    <h5 class="modal-title mb-0" id="eventModalTitle">Detail jadwal</h5>
                    <small class="text-muted" id="eventModalSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Tingkat 1: jam-jam kelas --}}
                <div id="levelJam" class="d-none"></div>
                {{-- Tingkat 2: tutor & murid satu kelas --}}
                <div id="levelKelas" class="d-none"></div>
                {{-- Tingkat 3: detail Holiday Class atau satu pengajuan replacement --}}
                <div id="levelDetail" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <a href="#" id="eventModalLink" class="btn btn-primary d-none"><i class="bi bi-pencil me-1"></i> Kelola Replacement</a>
                {{-- Hapus kelas — hanya muncul saat modal sedang menampilkan satu
                     kelas (tingkat 2), supaya tak pernah tersedia di daftar jam
                     yang belum menunjuk kelas tertentu. --}}
                <form method="POST" id="eventModalDelete" class="d-none m-0"
                      onsubmit="return confirm('Hapus kelas ' + (this.dataset.nama || 'ini') + '?\n\nKelas yang masih punya murid, riwayat absensi, atau pengajuan replacement akan ditolak sistem.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> Hapus kelas</button>
                </form>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
{{-- Judul rentang, nama bulan, & teks "+N lainnya" ikut bahasa aplikasinya.
     Kalau berkas ini gagal dimuat, kalender jatuh kembali ke bahasa Inggris —
     tidak rusak, hanya tidak diterjemahkan. Kepala kolom hari tidak bergantung
     padanya: lihat dayHeaderContent. --}}
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/id.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const events = @json($events);
    const rosters = @json($rosters ?? []);
    const createUrl = @json(route('schedules.create'));

    // Jam buka sanggar (WITA) & panjang satu sesi, dari App\Models\ClassRoom.
    const jamBuka = @json(\App\Models\ClassRoom::SLOT_START.':00');
    const jamTutup = @json(\App\Models\ClassRoom::SLOT_END.':00');
    const slotDurasi = @json($slotDurasi);
    const slotMenit = @json($slotMenit);
    // Jam mulai tiap pita berlabel. Klik di garis setengah jam dijepret ke pita
    // yang memuatnya — lihat pitaUntuk().
    const pitaJam = @json(\App\Models\ClassRoom::slots());
    const modalEl = document.getElementById('eventModal');
    const modal = new bootstrap.Modal(modalEl);

    const onlyAvailable = document.getElementById('onlyAvailable');
    const studentSel = document.getElementById('replacementStudent');
    const banner = document.getElementById('replacementBanner');

    const judul = document.getElementById('eventModalTitle');
    const subJudul = document.getElementById('eventModalSubtitle');
    const levelJam = document.getElementById('levelJam');
    const levelKelas = document.getElementById('levelKelas');
    const levelDetail = document.getElementById('levelDetail');
    const tombolKembali = document.getElementById('drillBack');
    const labelKembali = document.getElementById('drillBackLabel');
    const link = document.getElementById('eventModalLink');
    const hapus = document.getElementById('eventModalDelete');

    const escapeHtml = (teks) => String(teks ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);

    const tanggalPanjang = (iso) => new Date(iso + 'T00:00:00')
        .toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    /** "14 September" — untuk judul yang tanggal lengkapnya sudah tertulis di subjudul. */
    const tanggalPendek = (iso) => new Date(iso + 'T00:00:00')
        .toLocaleDateString('id-ID', { day: 'numeric', month: 'long' });

    const menitDari = (jam) => Number(jam.slice(0, 2)) * 60 + Number(jam.slice(3, 5));

    const jamDari = (menit) => String(Math.floor(menit / 60)).padStart(2, '0')
        + ':' + String(menit % 60).padStart(2, '0');

    /** "09:00–10:30" — satu pita jam, dipakai judul modal & tombol kembali. */
    const labelSlot = (mulai) => mulai + '–' + jamDari(menitDari(mulai) + slotMenit);

    /**
     * Pita berlabel yang memuat sebuah jam: pitaUntuk('16:00') === '15:00'.
     *
     * Garis kalender kini tiap setengah jam, jadi klik bisa mendarat di 15:30 —
     * jam yang tidak pernah jadi awal pita mana pun. Yang dibuka tetap pita
     * berlabelnya, karena itulah satuan yang dilihat admin.
     */
    function pitaUntuk(jam) {
        let pita = pitaJam[0];
        pitaJam.forEach(function (awal) {
            if (awal <= jam) pita = awal;
        });

        return pita;
    }

    // ── Murid yang sedang dipilih untuk "cari kelas pengganti" (atau null) ──
    function currentStudent() {
        const opt = studentSel.selectedOptions[0];
        if (!opt || !opt.value) return null;
        return { id: opt.value, name: opt.dataset.name, cat: opt.dataset.category };
    }

    // Slot yang bisa dipakai murid: kelas reguler & masih available.
    // Tipe kelas boleh berbeda — murid diizinkan replacement lintas tipe.
    function matchesStudent(ev) {
        const p = ev.extendedProps || {};
        return p.type === 'Kelas Reguler' && p.available === true;
    }

    /** Slot setipe dengan murid — dipakai sebagai penanda, bukan penyaring. */
    function sameType(ev, stu) {
        return (ev.extendedProps || {}).cat === stu.cat;
    }

    // ── Penelusuran bertingkat ──────────────────────────────────────

    function tampilkanLevel(aktif, opsiKembali) {
        [levelJam, levelKelas, levelDetail].forEach(function (el) {
            el.classList.toggle('d-none', el !== aktif);
        });

        // Tombol hapus melekat pada satu kelas, jadi ikut tingkat kelas saja —
        // dipasang di sini agar tak ada jalan masuk yang lupa menyembunyikannya.
        hapus.classList.toggle('d-none', aktif !== levelKelas);

        if (opsiKembali) {
            labelKembali.textContent = opsiKembali.label;
            tombolKembali.classList.remove('d-none');
            tombolKembali.onclick = opsiKembali.aksi;
        } else {
            tombolKembali.classList.add('d-none');
            tombolKembali.onclick = null;
        }
    }

    /** Sesi kelas reguler pada satu tanggal, terurut jam. */
    function sesiPadaTanggal(tanggal) {
        return events
            .filter(function (ev) {
                return (ev.extendedProps || {}).type === 'Kelas Reguler' && ev.start.slice(0, 10) === tanggal;
            })
            .sort(function (a, b) { return a.start.localeCompare(b.start); });
    }

    /** Baris jam: dipakai daftar per tanggal maupun daftar seluruh pekan. */
    function barisJam(jam, roster, keterangan, onClick) {
        const tombol = document.createElement('button');
        tombol.type = 'button';
        tombol.className = 'drill-row mb-2';
        tombol.innerHTML =
            `<span class="drill-time">${escapeHtml(jam)}</span>
             <span class="flex-grow-1">
                 <span class="fw-semibold text-capitalize">${escapeHtml(roster.category)}</span>
                 <span class="text-muted small ms-1">${escapeHtml(roster.code)}</span>
                 <br><span class="small text-muted">
                     <i class="bi bi-person-video3 me-1"></i>${escapeHtml(roster.tutor || 'Tutor kosong')}
                     ${keterangan ? ' · ' + escapeHtml(keterangan) : ''}
                 </span>
             </span>
             <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                 ${roster.enrolled} / ${roster.capacity} murid
             </span>
             <i class="bi bi-chevron-right text-muted"></i>`;
        tombol.addEventListener('click', onClick);

        return tombol;
    }

    /** Tingkat 1 — jam kelas pada satu tanggal. */
    function bukaHari(tanggal) {
        const sesi = sesiPadaTanggal(tanggal);

        judul.textContent = tanggalPanjang(tanggal);
        subJudul.textContent = sesi.length ? sesi.length + ' kelas berjalan hari ini' : '';
        link.classList.add('d-none');
        levelJam.innerHTML = '';

        if (!sesi.length) {
            levelJam.innerHTML = '<div class="drill-empty"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>Tidak ada kelas pada tanggal ini.</div>';
        }

        sesi.forEach(function (ev) {
            const p = ev.extendedProps || {};
            const roster = rosters[p.classId];
            if (!roster) return;

            const jam = ev.end
                ? ev.start.slice(11, 16) + '–' + ev.end.slice(11, 16)
                : ev.start.slice(11, 16);
            const titipan = (p.guests || []).length;
            const keterangan = titipan ? titipan + ' murid titipan' : (p.available ? '' : p.availability);

            levelJam.appendChild(barisJam(jam, roster, keterangan, function () {
                bukaKelas(p.classId, { tanggal: tanggal, guests: p.guests || [] });
            }));
        });

        tampilkanLevel(levelJam, null);
        modal.show();
    }

    /** Tingkat 1 — kelas yang berjalan pada satu petak jam di satu tanggal.

        Ini pintu masuk yang dipakai tampilan pekan: pertanyaannya bukan "hari
        Rabu ada apa saja" melainkan "Rabu jam 9 siapa yang mengajar dan kelas
        apa", dan itu satu petak, bukan satu hari. */
    function bukaSlot(tanggal, jamMulai) {
        const awal = menitDari(jamMulai);
        const akhir = awal + slotMenit;

        // Sesi yang mulai di dalam petak ini. Kelas yang jamnya nanggung tetap
        // ikut ke petak tempat ia dimulai, bukan hilang.
        const sesi = sesiPadaTanggal(tanggal).filter(function (ev) {
            const mulai = menitDari(ev.start.slice(11, 16));

            return mulai >= awal && mulai < akhir;
        });

        judul.textContent = labelSlot(jamMulai);
        subJudul.textContent = tanggalPanjang(tanggal)
            + (sesi.length ? ' · ' + sesi.length + ' kelas berjalan' : '');
        link.classList.add('d-none');
        levelJam.innerHTML = '';

        if (!sesi.length) {
            levelJam.innerHTML = '<div class="drill-empty"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>Tidak ada kelas di jam ini.</div>';
        }

        sesi.forEach(function (ev) {
            const p = ev.extendedProps || {};
            const roster = rosters[p.classId];
            if (!roster) return;

            const titipan = (p.guests || []).length;
            const keterangan = titipan ? titipan + ' murid titipan' : (p.available ? '' : p.availability);

            levelJam.appendChild(barisJam(roster.time, roster, keterangan, function () {
                bukaKelas(p.classId, { tanggal: tanggal, slot: jamMulai, guests: p.guests || [] });
            }));
        });

        tampilkanLevel(levelJam, null);
        modal.show();
    }

    /** Tingkat 1 alternatif — seluruh jam kelas dalam sepekan (tombol "Ubah"). */
    function bukaSemuaJam() {
        const urutHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        const daftar = Object.values(rosters).sort(function (a, b) {
            const beda = urutHari.indexOf(a.dayName) - urutHari.indexOf(b.dayName);
            return beda !== 0 ? beda : a.time.localeCompare(b.time);
        });

        judul.textContent = 'Semua Jam Kelas';
        subJudul.textContent = 'Pilih jam untuk melihat tutor & muridnya, lalu klik nama murid untuk membuka datanya.';
        link.classList.add('d-none');
        levelJam.innerHTML = '';

        if (!daftar.length) {
            levelJam.innerHTML = '<div class="drill-empty"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>Belum ada kelas terdaftar.</div>';
        }

        let hariTerakhir = null;
        daftar.forEach(function (roster) {
            if (roster.dayName !== hariTerakhir) {
                hariTerakhir = roster.dayName;
                const kepala = document.createElement('div');
                kepala.className = 'text-uppercase text-muted fw-bold small mt-3 mb-2';
                kepala.textContent = 'Setiap ' + roster.dayName;
                levelJam.appendChild(kepala);
            }

            levelJam.appendChild(barisJam(roster.time, roster, roster.availability, function () {
                bukaKelas(roster.id, { kembaliKeSemua: true });
            }));
        });

        tampilkanLevel(levelJam, null);
        modal.show();
    }

    /** Tingkat 2 — tutor & murid satu kelas. */
    function bukaKelas(classId, konteks) {
        const roster = rosters[classId];
        if (!roster) return;

        konteks = konteks || {};
        judul.textContent = roster.category;
        subJudul.textContent = roster.code + ' · ' + (konteks.tanggal ? tanggalPanjang(konteks.tanggal) : roster.schedule);

        const titipan = konteks.guests || [];
        let html =
            `<div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-muted">Tutor</div>
                        <div class="fw-semibold">${escapeHtml(roster.tutor || 'Belum ada tutor')}</div>
                        ${roster.tutorPhone ? `<div class="small text-muted">${escapeHtml(roster.tutorPhone)}</div>` : ''}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-muted">Jam</div>
                        <div class="fw-semibold">${escapeHtml(roster.time)}</div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-muted">Terisi</div>
                        <div class="fw-semibold">${roster.enrolled} / ${roster.capacity}</div>
                    </div>
                </div>
            </div>`;

        if (!roster.students.length) {
            html += '<div class="drill-empty"><i class="bi bi-people fs-3 d-block mb-2"></i>Belum ada murid di kelas ini.</div>';
        } else {
            html +=
                `<div class="fw-semibold mb-2"><i class="bi bi-people me-1"></i>Murid (${roster.students.length})</div>
                 <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                 <thead><tr><th>Nama</th><th>ID</th><th>Kategori</th><th>Wali</th><th class="text-end">Data</th></tr></thead><tbody>` +
                roster.students.map(function (murid) {
                    return `<tr class="drill-murid" data-url="${escapeHtml(murid.url)}" style="cursor:pointer;">
                        <td class="fw-semibold">${escapeHtml(murid.name)}</td>
                        <td class="small text-muted">${escapeHtml(murid.studentId)}</td>
                        <td class="small text-capitalize">${escapeHtml(murid.category || '-')}</td>
                        <td class="small">${escapeHtml(murid.parent || '-')}</td>
                        <td class="text-end"><i class="bi bi-pencil-square text-primary"></i></td>
                    </tr>`;
                }).join('') +
                '</tbody></table></div>';
        }

        if (titipan.length) {
            // Tanggalnya disebut, bukan "hari ini": modal ini bisa dibuka untuk sesi
            // pekan depan, dan replacement selalu melekat pada satu tanggal tertentu.
            const kapan = konteks.tanggal ? tanggalPendek(konteks.tanggal) : 'hari itu';

            html +=
                `<div class="fw-semibold mt-3 mb-2"><i class="bi bi-arrow-left-right me-1"></i>Murid replacement ${escapeHtml(kapan)} (${titipan.length})</div>` +
                titipan.map(function (murid, i) {
                    return `<button type="button" class="drill-row mb-2 drill-titipan" data-titipan="${i}"
                        title="Lihat detail replacement ${escapeHtml(murid.name)}">
                        <span class="flex-grow-1"><span class="fw-semibold">${escapeHtml(murid.name)}</span>
                        <span class="small text-muted ms-1">${escapeHtml(murid.studentId)}</span></span>
                        <i class="bi bi-chevron-right text-muted"></i></button>`;
                }).join('');
        }

        levelKelas.innerHTML = html;

        // Klik murid tetap → form datanya, yang memang sudah punya pilihan kategori
        // kelas (coloring, drawing, dst.) — satu tutor bisa memegang beberapa.
        levelKelas.querySelectorAll('.drill-murid').forEach(function (baris) {
            baris.addEventListener('click', function () { window.location = baris.dataset.url; });
        });

        // Murid replacement → detail pengajuannya, satu tingkat lebih dalam di
        // modal yang sama. Bukan langsung ke form ubah: yang dicari admin lebih
        // dulu adalah keterangannya — dari kelas mana, jam berapa, kenapa — dan
        // berpindah halaman untuk membacanya berarti kehilangan tempatnya.
        levelKelas.querySelectorAll('.drill-titipan').forEach(function (baris) {
            baris.addEventListener('click', function () {
                bukaTitipan(titipan[Number(baris.dataset.titipan)], classId, konteks);
            });
        });

        link.href = roster.editUrl;
        link.innerHTML = '<i class="bi bi-pencil me-1"></i> Ubah jadwal kelas';
        link.classList.remove('d-none');

        // Sasaran hapus ikut kelas yang sedang dibuka; namanya dipakai di
        // konfirmasi supaya admin tahu persis kelas mana yang akan hilang.
        hapus.action = roster.deleteUrl;
        hapus.dataset.nama = roster.category + ' (' + roster.code + ' · ' + roster.schedule + ')';

        // Kembali ke tempat asalnya, bukan selalu ke satu hari penuh: yang dibuka
        // dari petak jam harus kembali ke petak itu juga.
        const kembali = konteks.kembaliKeSemua
            ? { label: 'Semua jam kelas', aksi: bukaSemuaJam }
            : (konteks.slot
                ? { label: labelSlot(konteks.slot), aksi: function () { bukaSlot(konteks.tanggal, konteks.slot); } }
                : (konteks.tanggal ? { label: tanggalPanjang(konteks.tanggal), aksi: function () { bukaHari(konteks.tanggal); } } : null));

        tampilkanLevel(levelKelas, kembali);
        modal.show();
    }

    /**
     * Tingkat 3 — detail satu pengajuan replacement, dibuka dari baris murid
     * titipan di daftar kelas.
     *
     * Isinya persis keterangan yang dulu muncul saat badge replacement di
     * kalender diklik. Badge-nya yang hilang, bukan keterangannya: ia sekarang
     * tinggal di dalam kelas yang dititipi, tempat pertanyaannya memang muncul.
     */
    function bukaTitipan(murid, classId, konteks) {
        if (!murid) return;

        judul.textContent = 'Replacement: ' + murid.name;
        subJudul.textContent = murid.studentId || '';

        // "Senin, 14 September 2026 pukul 10.00" — jam pakai titik, seperti
        // penulisan waktu Indonesia di seluruh aplikasi.
        const waktu = tanggalPanjang(murid.date)
            + (murid.time ? ' pukul ' + murid.time.replace(':', '.') : '');

        levelDetail.innerHTML =
            `<p class="mb-1"><strong>Jenis:</strong> Replacement Class</p>
             <p class="mb-1"><strong>Waktu:</strong> ${escapeHtml(waktu)}</p>
             <p class="mb-1"><strong>Kelas asal (sebelumnya):</strong> ${escapeHtml(murid.originClass || '-')}</p>
             <p class="mb-1"><strong>Kelas baru (sekarang):</strong> ${escapeHtml(murid.newClass || '-')}</p>
             <p class="mb-1"><strong>Status:</strong> ${escapeHtml(murid.status || '-')}</p>
             <p class="mb-1"><strong>Alasan:</strong> ${escapeHtml(murid.reason || '-')}</p>`;

        link.href = murid.editUrl;
        link.innerHTML = '<i class="bi bi-pencil me-1"></i> Kelola Replacement';
        link.classList.remove('d-none');

        // Kembali ke kelas yang dititipi, bukan ke kalender: daftar muridnya
        // biasanya belum selesai dibaca saat satu nama diklik.
        const roster = rosters[classId];
        tampilkanLevel(levelDetail, {
            label: roster ? roster.category + ' · ' + roster.code : 'Kembali',
            aksi: function () { bukaKelas(classId, konteks); },
        });
        modal.show();
    }

    /** Detail event non-kelas: Holiday Class. */
    function bukaDetail(info) {
        const p = info.event.extendedProps;
        judul.textContent = info.event.title;
        subJudul.textContent = '';

        let rows = `<p class="mb-1"><strong>Jenis:</strong> ${p.type}</p>`;
        const waktu = info.event.allDay
            ? info.event.start.toLocaleDateString('id-ID', { dateStyle: 'full' })
            : info.event.start.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' });
        rows += `<p class="mb-1"><strong>Waktu:</strong> ${waktu}</p>`;
        if (p.code)         rows += `<p class="mb-1"><strong>Kode kelas:</strong> ${p.code}</p>`;
        if (p.schedule)     rows += `<p class="mb-1"><strong>Jadwal rutin:</strong> ${p.schedule}</p>`;
        if (p.time)         rows += `<p class="mb-1"><strong>Jam:</strong> ${p.time}</p>`;
        if (p.category)     rows += `<p class="mb-1"><strong>Kategori:</strong> ${p.category}</p>`;
        if (p.tutor)        rows += `<p class="mb-1"><strong>Tutor:</strong> ${p.tutor}</p>`;
        if (p.occupancy)    rows += `<p class="mb-1"><strong>Terisi:</strong> ${p.occupancy}</p>`;
        if (p.availability) rows += `<p class="mb-1"><strong>Ketersediaan:</strong> ${p.availability}</p>`;
        if (p.note && p.note !== '-') rows += `<p class="mb-1"><strong>Catatan:</strong> ${p.note}</p>`;
        levelDetail.innerHTML = rows;

        const stu = currentStudent();
        if (stu && matchesStudent(info.event)) {
            // Beda tipe tidak menghalangi, tapi perlu disadari admin sebelum mengajukan.
            if (!sameType(info.event, stu)) {
                levelDetail.insertAdjacentHTML('beforeend',
                    '<div class="alert alert-warning small mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Slot ini bertipe <strong>' + p.category + '</strong>, sedangkan murid bertipe <strong>' + stu.cat + '</strong>. Tetap bisa diajukan.</div>');
            }
            // Mode cari pengganti: langsung ajukan replacement untuk murid & slot ini.
            const start = info.event.start;
            const params = new URLSearchParams({
                student_id: stu.id,
                class_id: p.classId,
                replacement_date: start.toLocaleDateString('en-CA'), // YYYY-MM-DD lokal
                replacement_time: start.toTimeString().slice(0, 5),
            });
            link.href = createUrl + '?' + params.toString();
            link.innerHTML = '<i class="bi bi-send me-1"></i> Ajukan Replacement untuk ' + stu.name;
            link.classList.remove('d-none');
        } else if (info.event.url) {
            link.href = info.event.url;
            link.innerHTML = '<i class="bi bi-pencil me-1"></i> ' + (p.linkLabel || 'Kelola jadwal');
            link.classList.remove('d-none');
        } else {
            link.classList.add('d-none');
        }

        tampilkanLevel(levelDetail, null);
        modal.show();
    }

    /**
     * Tandai badge yang sedang dibuka.
     *
     * Modalnya menutupi kalender, jadi tanpa ini admin kehilangan tempatnya
     * begitu modal ditutup — terutama di jam yang berisi dua badge berdampingan.
     */
    function tandaiTerpilih(el) {
        document.querySelectorAll('#calendar .fc-event.is-selected')
            .forEach(function (n) { n.classList.remove('is-selected'); });
        if (el) el.classList.add('is-selected');
    }

    /**
     * Tautan "+N" juga <a> tanpa href. Dibereskan setelah tiap penggambaran,
     * bukan sekali saat muat: FullCalendar membuatnya ulang tiap ganti pekan.
     */
    function aksesTautanLain() {
        document.querySelectorAll('#calendar .fc-timegrid-more-link').forEach(function (el) {
            if (el.dataset.siap) return;
            el.dataset.siap = '1';
            el.setAttribute('tabindex', '0');
            el.setAttribute('role', 'button');
            el.setAttribute('aria-label', el.textContent.trim() + ' di jam ini');
            el.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                el.click();
            });
        });
    }

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        // Pekan per jam, bukan bulan: jadwal sanggar adalah petak hari x jam yang
        // sama tiap pekan, dan itu yang dibaca admin. Tampilan bulan & daftar
        // tetap tersedia di kanan untuk melihat rentang yang lebih panjang.
        initialView: 'timeGridWeek',
        locale: 'id',
        firstDay: 1,
        height: 'auto',
        eventDisplay: 'block',
        displayEventTime: true,
        // Satu petak jam hanya memuat sedikit teks, jadi kolomnya tak boleh
        // dibagi lagi oleh event yang saling tindih: dua jadwal di jam yang sama
        // berdiri berdampingan dengan lebar penuh, bukan bertumpuk sebagian.
        slotEventOverlap: false,
        // Satu kolom hari lebarnya sekitar 130px. Dibagi dua masih terbaca;
        // dibagi tiga tinggal 44px dan tak ada satu kata pun yang muat — yang
        // tersisa cuma "Pr… Art". Jadi paling banyak dua yang berdampingan, dan
        // sisanya turun ke tautan "+N" yang membuka daftarnya.
        eventMaxStack: 2,
        // "+1" telanjang tidak menyebutkan apa-apa tentang dirinya. Ditulis
        // penuh beserta ikonnya supaya terbaca sebagai "ada satu lagi, klik
        // untuk melihat" — bukan sebagai potongan angka yang tercecer.
        moreLinkContent: function (arg) {
            return { html: '<i class="bi bi-chevron-down me-1"></i>' + arg.num + ' jadwal lagi' };
        },
        moreLinkClick: 'popover',
        // Yang berhak atas dua tempat itu kelasnya lebih dulu. Replacement
        // adalah seorang murid yang menumpang ke kelas yang sudah ada, bukan
        // jadwal yang berdiri sendiri — kalau ia menggeser kelasnya ke balik
        // "+N", yang hilang justru jawaban yang dicari admin.
        eventOrder: function (a, b) {
            const bobot = { 'Kelas Reguler': 0, 'Holiday Class': 1 };
            const wa = bobot[(a.extendedProps || {}).type] ?? 2;
            const wb = bobot[(b.extendedProps || {}).type] ?? 2;
            if (wa !== wb) return wa - wb;
            if (a.start && b.start && a.start - b.start !== 0) return a.start - b.start;

            return String(a.title).localeCompare(String(b.title));
        },
        // "Senin" di atas "7 Sep" — dua baris, bukan satu baris panjang yang
        // membuat kolom sempit ikut melebar. Ditulis sendiri lewat Intl, jadi
        // tidak menunggu bundel locale.
        dayHeaderContent: function (arg) {
            const hari = arg.date.toLocaleDateString('id-ID', { weekday: 'long' });
            const bungkus = document.createElement('div');
            bungkus.className = 'cal-dayhead';
            bungkus.innerHTML = '<span class="cal-dayhead-name">' + hari + '</span>';

            // Tanggalnya hanya di tampilan pekan. Di tampilan bulan satu kolom
            // mewakili lima tanggal sekaligus, jadi mencantumkan satu di antaranya
            // bukan cuma mubazir — ia menyebut tanggal yang keliru.
            if (arg.view.type.startsWith('timeGrid')) {
                const tanggal = arg.date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                bungkus.insertAdjacentHTML('beforeend', '<span class="cal-dayhead-date">' + tanggal + '</span>');
            }

            return { domNodes: [bungkus] };
        },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'timeGridWeek,dayGridMonth,listMonth'
        },
        buttonText: { today: 'Hari Ini', month: 'Bulan', week: 'Minggu', list: 'Daftar' },
        // Sanggar buka 09:00-18:00 WITA dan tiap kelas 1,5 jam, jadi harinya
        // terbagi jadi enam petak yang selalu sama. Sumber angkanya satu:
        // konstanta di ClassRoom.
        slotMinTime: jamBuka,
        slotMaxTime: jamTutup,
        // Digambar tiap 30 menit, tapi hanya berlabel tiap 1,5 jam: grid tetap
        // terbaca sebagai enam pita, sementara kelas 16:00 tidak lagi harus
        // berbohong tentang jamnya.
        slotDuration: '00:30:00',
        slotLabelInterval: slotDurasi,
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        // Slot lama yang jam selesainya belum pernah diisi tetap tergambar
        // setinggi satu sesi, bukan sebagai garis setipis nol menit.
        defaultTimedEventDuration: slotDurasi,
        allDaySlot: false,
        expandRows: true,
        nowIndicator: true,
        events: events,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        // Tingkat pertama. Di tampilan pekan yang diklik adalah satu petak jam
        // (dateStr membawa jamnya); di tampilan bulan hanya ada tanggal, jadi
        // yang dibuka seluruh jam hari itu.
        dateClick: function (info) {
            if (info.dateStr.length > 10) {
                bukaSlot(info.dateStr.slice(0, 10), pitaUntuk(info.dateStr.slice(11, 16)));

                return;
            }

            bukaHari(info.dateStr.slice(0, 10));
        },
        // Petak jam berhenti di jam tutup, jadi jadwal di luar itu tidak
        // tergambar sama sekali. Yang tak terlihat harus tetap terhitung —
        // lihat catatanLuarJam().
        datesSet: function (info) {
            catatanLuarJam(info);
            // Tautan "+N" digambar setelah kejadian ini, jadi ditunggu satu frame.
            requestAnimationFrame(aksesTautanLain);
        },
        // "Jam 9 itu siapa gurunya" adalah pertanyaan pertama tentang sebuah
        // petak, jadi jawabannya ditulis di petaknya — bukan disimpan di balik
        // satu klik. Hanya di tampilan pekan: di tampilan bulan & daftar,
        // satu baris per kejadian tidak punya ruang untuk baris kedua.
        eventDidMount: function (arg) {
            const p = arg.event.extendedProps || {};
            if (! arg.view.type.startsWith('timeGrid')) return;

            // Warna eventnya diturunkan jadi variabel supaya CSS bisa meramunya
            // sendiri — jadi satu sumber warna (ScheduleCalendar) tetap dipakai
            // dua tampilan dengan kadar yang berbeda.
            arg.el.style.setProperty('--ev', arg.event.backgroundColor || '#0EA5E9');

            // Kolom yang dibagi dua jadwal tidak selalu muat memuat nama penuh.
            // Yang terpotong di layar harus tetap bisa dibaca tanpa membuka
            // apa-apa — keterangan lengkapnya menempel di bloknya sendiri.
            arg.el.title = [
                p.time || '',
                arg.event.title,
                p.tutor && p.tutor !== '-' ? 'Tutor: ' + p.tutor : '',
                p.occupancy ? 'Terisi ' + p.occupancy : '',
            ].filter(Boolean).join(' · ');

            // Badge ini <a> tanpa href, jadi papan ketik melewatinya begitu saja.
            // Diberi peran & urutan tab sendiri, lengkap dengan nama yang dibaca
            // pembaca layar — isinya sama dengan tooltipnya, karena pertanyaan
            // yang dijawab keduanya juga sama.
            arg.el.setAttribute('tabindex', '0');
            arg.el.setAttribute('role', 'button');
            arg.el.setAttribute('aria-label', arg.el.title);
            arg.el.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                arg.el.click();
            });

            // Jam di dalam blok mengulang label baris di kolom kiri — petak yang
            // sudah bernama "10:30" tak perlu menuliskannya lagi di dalam, apalagi
            // dengan ruang sesempit ini.
            const waktu = arg.el.querySelector('.fc-event-time');
            if (waktu) waktu.remove();

            const judulEl = arg.el.querySelector('.fc-event-title');
            if (! judulEl) return;

            if (p.type !== 'Kelas Reguler' || ! p.tutor || p.tutor === '-') return;

            // Nama tutor hanya di sesi yang bloknya memang cukup tinggi.
            //
            // Sesi 60 menit setinggi ~51px: judul dua baris sudah menghabiskannya.
            // Memaksa baris tutor masuk ke sana berarti salah satunya terpotong,
            // dan yang lebih baik dikorbankan adalah tutornya — ia masih bisa
            // dibaca di tooltip badge dan di daftar saat petaknya diklik,
            // sedangkan nama kelas tidak punya cadangan semacam itu.
            const menit = arg.event.end
                ? (arg.event.end - arg.event.start) / 60000
                : slotMenit;
            if (menit < slotMenit) return;

            const baris = document.createElement('div');
            baris.className = 'fc-event-tutor';
            baris.textContent = p.tutor;

            // Ditaruh sebagai saudara judul, bukan di dalamnya: pembatas dua
            // baris pada judul ikut menghitung baris anaknya, dan nama tutor
            // akan memakan jatah nama kelas.
            const rangka = arg.el.querySelector('.fc-event-main-frame') || judulEl.parentElement;
            rangka.appendChild(baris);
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            tandaiTerpilih(info.el);
            const p = info.event.extendedProps || {};

            // Kelas reguler langsung ke tingkat kedua: tanggal & jamnya sudah
            // ditentukan oleh event yang diklik. Kecuali saat mode cari kelas
            // pengganti aktif — di sana yang dicari admin adalah tombol ajukan.
            if (p.type === 'Kelas Reguler' && !currentStudent()) {
                const mulai = info.event.start;

                bukaKelas(p.classId, {
                    tanggal: mulai.toLocaleDateString('en-CA'),
                    // Pita tempat sesi ini jatuh — supaya "kembali" mendarat di
                    // daftar jam yang sama, bukan di hari penuh.
                    slot: pitaUntuk(mulai.toTimeString().slice(0, 5)),
                    guests: p.guests || [],
                });

                return;
            }

            bukaDetail(info);
        }
    });
    calendar.render();

    // Kalender ini juga dirender di dalam panel yang bisa tersembunyi (tab di
    // Manajemen kelas). FullCalendar mengukur tinggi & lebarnya saat render, dan
    // di dalam elemen display:none hasilnya nol — halaman yang membukanya perlu
    // menyuruhnya mengukur ulang lewat instans ini.
    window.jadwalCalendar = calendar;

    // Tombol "Ubah" di kepala halaman masuk lewat pintu yang sama, hanya mulai
    // dari daftar seluruh jam alih-alih satu tanggal.
    ['btnUbahJadwal', 'btnReplacement'].forEach(function (id) {
        const tombol = document.getElementById(id);
        if (tombol) tombol.addEventListener('click', bukaSemuaJam);
    });

    // ── Penyaring tampilan kalender ─────────────────────────────────

    // Susun daftar event yang tampil sesuai mode aktif.
    // Prioritas: mode "cari kelas pengganti" (per murid) > toggle "hanya slot available".
    function visibleEvents() {
        const stu = currentStudent();
        if (stu) {
            // Slot cocok murid + Holiday Class (tetap ditampilkan sebagai konteks:
            // menandai hari studio sedang terpakai, walau bukan slot yang bisa
            // diajukan sebagai kelas pengganti).
            return events.filter(function (ev) {
                const p = ev.extendedProps || {};
                return matchesStudent(ev) || p.type === 'Holiday Class';
            });
        }
        if (!onlyAvailable.checked) return events;
        return events.filter(function (ev) {
            const p = ev.extendedProps || {};
            // Kelas reguler: 'available' sudah memuat penuh, ditutup, sudah lewat,
            // dan tutor kosong.
            if (p.type === 'Kelas Reguler') return p.available === true;
            // Jadwal lain (Holiday Class) yang sudah lewat adalah riwayat,
            // bukan agenda.
            return p.past !== true;
        });
    }

    /**
     * Jadwal di luar jam buka pada pekan yang sedang dilihat.
     *
     * Tampilan pekan hanya menggambar 09:00-18:00; kelas jam 07:00 atau Holiday
     * Class jam 19:00 tidak tergambar di mana pun. Kalender yang diam soal itu
     * membuat admin menyimpulkan hari itu kosong.
     */
    function catatanLuarJam(info) {
        const kotak = document.getElementById('luarJamBuka');
        if (!kotak) return;

        if (!info.view.type.startsWith('timeGrid')) {
            kotak.classList.add('d-none');

            return;
        }

        const buka = menitDari(jamBuka.slice(0, 5));
        const tutup = menitDari(jamTutup.slice(0, 5));
        const dari = info.startStr.slice(0, 10);
        const sampai = info.endStr.slice(0, 10); // eksklusif

        const luar = visibleEvents().filter(function (ev) {
            const tanggal = ev.start.slice(0, 10);
            if (tanggal < dari || tanggal >= sampai) return false;

            const mulai = menitDari(ev.start.slice(11, 16));

            return mulai < buka || mulai >= tutup;
        });

        kotak.classList.toggle('d-none', luar.length === 0);
        kotak.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>'
            + '<strong>' + luar.length + ' jadwal</strong> pekan ini jatuh di luar jam buka '
            + jamBuka.slice(0, 5) + '–' + jamTutup.slice(0, 5)
            + ', jadi tidak tergambar di petak. Buka tampilan <strong>Bulan</strong> atau '
            + '<strong>Daftar</strong> untuk melihatnya.';
    }

    function updateBanner() {
        const stu = currentStudent();
        if (!stu) { banner.classList.add('d-none'); banner.classList.remove('d-flex'); return; }
        document.getElementById('rbName').textContent = stu.name;
        document.getElementById('rbCategory').textContent = stu.cat;
        document.getElementById('rbCount').textContent = events.filter(matchesStudent).length;
        banner.classList.remove('d-none');
        banner.classList.add('d-flex');
        // Toggle "hanya available" tidak relevan saat mode ini aktif.
        onlyAvailable.disabled = true;
    }

    function applyFilter() {
        // Susun ulang event source agar perubahan pasti ter-render (berlaku di semua view & navigasi bulan).
        calendar.getEventSources().forEach(function (source) { source.remove(); });
        calendar.addEventSource(visibleEvents());
        updateBanner();
        // toLocaleDateString('en-CA'), bukan toISOString(): yang dibandingkan
        // tanggal lokal WITA, dan ISO menggesernya delapan jam ke belakang.
        catatanLuarJam({
            view: calendar.view,
            startStr: calendar.view.activeStart.toLocaleDateString('en-CA'),
            endStr: calendar.view.activeEnd.toLocaleDateString('en-CA'),
        });
    }

    onlyAvailable.addEventListener('change', applyFilter);
    studentSel.addEventListener('change', applyFilter);
    document.getElementById('rbClear').addEventListener('click', function () {
        studentSel.value = '';
        onlyAvailable.disabled = false;
        applyFilter();
    });

    // Terapkan filter saat load agar default "Hanya slot available" (checked) langsung berlaku.
    applyFilter();
});
</script>
@endpush
