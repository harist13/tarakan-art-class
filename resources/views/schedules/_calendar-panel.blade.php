{{-- Panel kalender jadwal: kelas reguler, Holiday Class, & replacement.

     Dipakai dua halaman — Kalender Jadwal dan tab di Manajemen Kelas. Isinya
     dipisah ke sini, bukan disalin, supaya keduanya tidak pernah menampilkan
     jadwal yang berbeda.

     Butuh: $events & $rosters (dari App\Support\ScheduleCalendar) serta $students. --}}
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

    /* Sel tanggal bisa diklik untuk membuka jam-jam hari itu — perlu terasa
       bisa diklik, bukan cuma bisa. */
    #calendar .fc-daygrid-day { cursor: pointer; }
    #calendar .fc-daygrid-day:hover { background-color: var(--surface-2); }

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
            <span class="fw-bold">Jadwal Kelas &amp; Replacement Class</span>
            <div class="cal-legend">
                <span class="legend-pill" style="background:rgba(14,165,233,.12); border-color:rgba(14,165,233,.35); color:#0369A1;">
                    <span class="legend-dot" style="background:#0EA5E9;"></span>Kelas (Available)
                </span>
                <span class="legend-pill" style="background:rgba(148,163,184,.16); border-color:rgba(148,163,184,.4); color:#475569;">
                    <span class="legend-dot" style="background:#94A3B8;"></span>Penuh / Ditutup
                </span>
                <span class="legend-pill" style="background:rgba(245,158,11,.14); border-color:rgba(245,158,11,.38); color:#B45309;">
                    <span class="legend-dot" style="background:#F59E0B;"></span>Repl. Pending
                </span>
                <span class="legend-pill" style="background:rgba(16,185,129,.14); border-color:rgba(16,185,129,.38); color:#047857;">
                    <span class="legend-dot" style="background:#10B981;"></span>Repl. Approved
                </span>
                <span class="legend-pill" style="background:rgba(239,68,68,.13); border-color:rgba(239,68,68,.35); color:#B91C1C;">
                    <span class="legend-dot" style="background:#EF4444;"></span>Repl. Rejected
                </span>
                <span class="legend-pill" style="background:rgba(192,38,211,.12); border-color:rgba(192,38,211,.35); color:#A21CAF;">
                    <span class="legend-dot" style="background:#C026D3;"></span>Holiday Class
                </span>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3 small">
            <span class="text-muted"><i class="bi bi-hand-index me-1"></i>Klik tanggal untuk melihat jam kelasnya, lalu tutor &amp; muridnya.</span>
            <div class="form-check form-switch ms-auto"
                 title="Menyembunyikan kelas yang penuh/ditutup, serta replacement &amp; Holiday Class yang jadwalnya sudah lewat.">
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
                    <h5 class="modal-title mb-0" id="eventModalTitle">Detail Jadwal</h5>
                    <small class="text-muted" id="eventModalSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Tingkat 1: jam-jam kelas --}}
                <div id="levelJam" class="d-none"></div>
                {{-- Tingkat 2: tutor & murid satu kelas --}}
                <div id="levelKelas" class="d-none"></div>
                {{-- Detail event non-kelas (Holiday Class & replacement) --}}
                <div id="levelDetail" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <a href="#" id="eventModalLink" class="btn btn-primary d-none"><i class="bi bi-pencil me-1"></i> Kelola Replacement</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const events = @json($events);
    const rosters = @json($rosters ?? []);
    const createUrl = @json(route('schedules.create'));
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

    const escapeHtml = (teks) => String(teks ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);

    const tanggalPanjang = (iso) => new Date(iso + 'T00:00:00')
        .toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

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
                    <div class="border rounded p-2">
                        <div class="small text-muted">Tutor</div>
                        <div class="fw-semibold">${escapeHtml(roster.tutor || 'Belum ada tutor')}</div>
                        ${roster.tutorPhone ? `<div class="small text-muted">${escapeHtml(roster.tutorPhone)}</div>` : ''}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="border rounded p-2">
                        <div class="small text-muted">Jam</div>
                        <div class="fw-semibold">${escapeHtml(roster.time)}</div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="border rounded p-2">
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
            html +=
                `<div class="fw-semibold mt-3 mb-2"><i class="bi bi-arrow-left-right me-1"></i>Murid titipan hari itu (${titipan.length})</div>` +
                titipan.map(function (murid) {
                    return `<button type="button" class="drill-row mb-2 drill-murid" data-url="${escapeHtml(murid.url)}">
                        <span class="flex-grow-1"><span class="fw-semibold">${escapeHtml(murid.name)}</span>
                        <span class="small text-muted ms-1">${escapeHtml(murid.studentId)}</span></span>
                        <i class="bi bi-pencil-square text-primary"></i></button>`;
                }).join('');
        }

        levelKelas.innerHTML = html;

        // Klik nama murid → form datanya, yang memang sudah punya pilihan kategori
        // kelas (coloring, drawing, dst.) — satu tutor bisa memegang beberapa.
        levelKelas.querySelectorAll('.drill-murid').forEach(function (baris) {
            baris.addEventListener('click', function () { window.location = baris.dataset.url; });
        });

        link.href = roster.editUrl;
        link.innerHTML = '<i class="bi bi-pencil me-1"></i> Ubah Jadwal Kelas';
        link.classList.remove('d-none');

        const kembali = konteks.kembaliKeSemua
            ? { label: 'Semua jam kelas', aksi: bukaSemuaJam }
            : (konteks.tanggal ? { label: tanggalPanjang(konteks.tanggal), aksi: function () { bukaHari(konteks.tanggal); } } : null);

        tampilkanLevel(levelKelas, kembali);
        modal.show();
    }

    /** Detail event non-kelas: Holiday Class & replacement. */
    function bukaDetail(info) {
        const p = info.event.extendedProps;
        judul.textContent = info.event.title;
        subJudul.textContent = '';

        let rows = `<p class="mb-1"><strong>Jenis:</strong> ${p.type}</p>`;
        const waktu = info.event.allDay
            ? info.event.start.toLocaleDateString('id-ID', { dateStyle: 'full' })
            : info.event.start.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' });
        rows += `<p class="mb-1"><strong>Waktu:</strong> ${waktu}</p>`;
        if (p.code)         rows += `<p class="mb-1"><strong>Kode Kelas:</strong> ${p.code}</p>`;
        if (p.schedule)     rows += `<p class="mb-1"><strong>Jadwal Rutin:</strong> ${p.schedule}</p>`;
        if (p.time)         rows += `<p class="mb-1"><strong>Jam:</strong> ${p.time}</p>`;
        if (p.category)     rows += `<p class="mb-1"><strong>Kategori:</strong> ${p.category}</p>`;
        if (p.tutor)        rows += `<p class="mb-1"><strong>Tutor:</strong> ${p.tutor}</p>`;
        if (p.occupancy)    rows += `<p class="mb-1"><strong>Terisi:</strong> ${p.occupancy}</p>`;
        if (p.availability) rows += `<p class="mb-1"><strong>Ketersediaan:</strong> ${p.availability}</p>`;
        if (p.originClass)  rows += `<p class="mb-1"><strong>Kelas Asal (sebelumnya):</strong> ${p.originClass}</p>`;
        if (p.newClass)     rows += `<p class="mb-1"><strong>Kelas Baru (sekarang):</strong> ${p.newClass}</p>`;
        if (p.status)       rows += `<p class="mb-1"><strong>Status:</strong> ${p.status}</p>`;
        if (p.reason)       rows += `<p class="mb-1"><strong>Alasan:</strong> ${p.reason}</p>`;
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
            // Label menyesuaikan jenis event; replacement tetap jadi default.
            link.innerHTML = '<i class="bi bi-pencil me-1"></i> ' + (p.linkLabel || 'Kelola Replacement');
            link.classList.remove('d-none');
        } else {
            link.classList.add('d-none');
        }

        tampilkanLevel(levelDetail, null);
        modal.show();
    }

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        firstDay: 1,
        height: 'auto',
        eventDisplay: 'block',
        displayEventTime: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: { today: 'Hari Ini', month: 'Bulan', week: 'Minggu', list: 'Daftar' },
        slotMinTime: '07:00:00',
        slotMaxTime: '21:00:00',
        nowIndicator: true,
        events: events,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        // Tingkat pertama: satu tanggal → jam-jam kelas hari itu.
        dateClick: function (info) {
            bukaHari(info.dateStr.slice(0, 10));
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const p = info.event.extendedProps || {};

            // Kelas reguler langsung ke tingkat kedua: tanggal & jamnya sudah
            // ditentukan oleh event yang diklik. Kecuali saat mode cari kelas
            // pengganti aktif — di sana yang dicari admin adalah tombol ajukan.
            if (p.type === 'Kelas Reguler' && !currentStudent()) {
                bukaKelas(p.classId, { tanggal: info.event.start.toLocaleDateString('en-CA'), guests: p.guests || [] });

                return;
            }

            bukaDetail(info);
        }
    });
    calendar.render();

    // Kalender ini juga dirender di dalam panel yang bisa tersembunyi (tab di
    // Manajemen Kelas). FullCalendar mengukur tinggi & lebarnya saat render, dan
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
            // Jadwal lain yang sudah lewat adalah riwayat, bukan agenda — termasuk
            // replacement pending yang terlewat, yang juga tidak bisa dipakai lagi.
            return p.past !== true;
        });
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
