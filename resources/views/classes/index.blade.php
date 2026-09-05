@extends('layouts.app')

@push('styles')
<style>
    /* Tabel kelas memakai pola dua baris yang sama dengan tabel murid: jawaban
       utamanya di baris pertama, keterangan pendukungnya di baris kedua yang
       lebih kecil. Enam kolom masih perlu padding yang dirapatkan — dengan 1rem
       bawaan, ruang kosongnya saja sudah memaksa tabel bergulir. */
    .kelas-table thead th,
    .kelas-table tbody td { padding: 0.75rem 0.7rem; }
    .kelas-table th { white-space: nowrap; }
    .kelas-table .cell-main { font-weight: 600; line-height: 1.3; }
    .kelas-table .cell-sub { font-size: 0.8rem; color: var(--text-muted); line-height: 1.35; }

    /* Kolom aksi menempel di kanan saat tabel digulir di layar sempit. */
    .kelas-table .col-actions { position: sticky; right: 0; background-color: var(--surface); }
    .kelas-table thead .col-actions { background-color: var(--surface-2); }
    .kelas-table .col-actions::before {
        content: ''; position: absolute; top: 0; bottom: 0; left: 0;
        border-left: 1px solid var(--border);
    }
    .kelas-table tbody tr:hover > td.col-actions { background-color: var(--surface-2); }

    /* Tabel tutor masih enam kolom: padding bawaan 1rem membuatnya bergulir
       pada layar yang sebenarnya cukup lebar. */
    #panelTutor .table th,
    #panelTutor .table td {
        padding: 0.75rem 0.6rem;
        word-break: break-word;
    }

    /* Bilah penyaring di kepala kartu: melebar sendiri di layar sempit alih-alih
       memaksa kartunya lebih lebar dari layar. */
    @media (max-width: 767.98px) {
        .card-header form[data-live] {
            width: 100%;
        }
        .card-header form[data-live] > .input-group,
        .card-header form[data-live] > .form-select {
            width: 100% !important;
        }
    }
</style>
@endpush

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold" id="pageTitle">Manajemen kelas & tutor</h1>
    <div class="d-flex gap-2">
        <button id="btnAddTutor" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tutorModal" style="display:none;"><i class="bi bi-person-plus"></i> Tambah tutor</button>
        <button type="button" id="btnReplacement" class="btn btn-sm btn-primary shadow-sm" style="display:none;"><i class="bi bi-pencil-square"></i> Ubah</button>
        <a id="btnAddClass" href="{{ route('classes.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Tambah kelas</a>
    </div>
</div>

{{-- Switch panel: Kelas <-> Tutor <-> Kalender.

     Dua yang pertama berpindah di sisi klien karena datanya sudah dirender.
     Kalender berupa tautan biasa: eventnya ratusan dan hanya disusun saat panel
     itu memang diminta -- lihat ClassRoomController::index. --}}
<div class="btn-group mb-4 shadow-sm flex-wrap" role="group" aria-label="Pilih panel">
    <input type="radio" class="btn-check" name="panelToggle" id="toggleKelas" autocomplete="off" @checked($tab === 'kelas')>
    <label class="btn btn-outline-primary" for="toggleKelas"><i class="bi bi-easel2 me-1"></i> Manajemen kelas</label>
    <input type="radio" class="btn-check" name="panelToggle" id="toggleTutor" autocomplete="off" @checked($tab === 'tutor')>
    <label class="btn btn-outline-primary" for="toggleTutor"><i class="bi bi-person-video3 me-1"></i> Manajemen tutor</label>
    <a href="{{ route('classes.index', ['tab' => 'kalender']) }}" id="toggleKalender"
        class="btn btn-outline-primary @if($tab === 'kalender') active @endif">
        <i class="bi bi-calendar3-week me-1"></i> Kalender jadwal
    </a>
</div>

<div class="card" id="panelKelas" @if($tab === 'tutor') style="display:none;" @endif>
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Daftar kelas</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:180px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari kelas...">
            </div>
            <select name="category" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" @selected($category === $cat)>{{ ucfirst($cat) }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua status</option>
                <option value="tersedia" @selected($status === 'tersedia')>Tersedia</option>
                <option value="penuh" @selected($status === 'penuh')>Penuh</option>
                <option value="tanpa-tutor" @selected($status === 'tanpa-tutor')>Tutor kosong</option>
                <option value="ditutup" @selected($status === 'ditutup')>Ditutup</option>
            </select>
            <select name="day" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua hari</option>
                @foreach(\App\Models\ClassRoom::DAY_NAMES as $dow => $label)
                    <option value="{{ $dow }}" @selected($day === (string) $dow)>{{ $label }}</option>
                @endforeach
            </select>
            @if($search !== '' || $category !== '' || $status !== '' || $day !== '')
                <a href="{{ route('classes.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        {{-- Filter hari menyembunyikan kelas sekali jalan yang sudah lewat, jadi
             perlu dinyatakan — baris yang hilang tanpa penjelasan membingungkan. --}}
        @if($day !== '' && is_numeric($day))
            <div class="alert alert-light border small d-flex align-items-center gap-2 py-2">
                <i class="bi bi-funnel"></i>
                <span>
                    Kelas yang jalan <strong>hari {{ \App\Models\ClassRoom::DAY_NAMES[(int) $day] ?? '-' }}</strong>, diurutkan per jam.
                    Kelas sekali jalan yang tanggalnya sudah lewat tidak ditampilkan —
                    <a href="{{ route('classes.index') }}" class="alert-link">lihat semua kelas</a>.
                </span>
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 kelas-table">
                <thead>
                    <tr>
                        <th>Kelas</th>
                        <th>Jadwal &amp; tutor</th>
                        <th>Ketersediaan</th>
                        <th class="text-end" title="Iuran bulanan + uang pendaftaran (ditagih sekali saat murid mendaftar)">Biaya</th>
                        <th class="text-end col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classes as $class)
                        @php
                            $av = $class->availability();
                            // Sifat jadwal (berulang / sekali jalan) & sesi berikutnya adalah
                            // keterangan, bukan yang dicari admin saat memindai tabel — jadi
                            // dititipkan ke tooltip agar barisnya tetap dua baris.
                            $catatanJadwal = ! $class->is_recurring
                                ? 'Kelas sekali jalan — tidak berulang tiap pekan.'
                                : (($sesi = $class->nextOccurrence())
                                    ? 'Kelas berulang tiap pekan. Sesi berikutnya '.$sesi->format('d M Y').'.'
                                    : 'Kelas berulang tiap pekan. Belum ada sesi mendatang.');
                        @endphp
                        <tr>
                            {{-- Kode di depan nama: kode itulah yang dipakai admin saat
                                 menyebut sebuah kelas, sedangkan tipenya keterangan. --}}
                            <td>
                                <div class="cell-main">
                                    {{ $class->class_code }} - <span class="text-capitalize">{{ $class->class_category }}</span>
                                </div>
                                {{-- Tipe kelas ditulis polos, tanpa badge berwarna: kolom
                                     Ketersediaan sudah memakai warna, dan dua penanda berwarna
                                     dalam satu baris saling berebut perhatian. --}}
                                <div class="cell-sub">{{ $class->typeLabel() }}</div>
                            </td>
                            {{-- "Kapan & siapa yang mengajar" dibaca sekali jalan, jadi tutor
                                 tidak lagi berdiri sebagai kolom sendiri. --}}
                            <td>
                                <div class="d-flex align-items-center gap-2" title="{{ $catatanJadwal }}">
                                    <span>{{ $class->scheduleLabel() }}</span>
                                    <i class="bi bi-{{ $class->is_recurring ? 'arrow-repeat' : 'calendar-x' }} text-muted small"></i>
                                </div>
                                <div class="cell-sub">
                                    @if($class->tutor)
                                        <i class="bi bi-person me-1"></i>{{ $class->tutor->name }}
                                    @else
                                        {{-- Tanpa warna khusus: kolom Ketersediaan sudah menandai
                                             keadaan ini dengan badge "Tutor kosong". --}}
                                        <i class="bi bi-person-dash me-1"></i>Tutor belum ditentukan
                                    @endif
                                </div>
                            </td>
                            {{-- Kolom kapasitas dilepas: badge di bawah ini sudah menyebut sisa
                                 kursinya, dan jumlah terisi/kapasitas ada di form edit kelas. --}}
                            <td>
                                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: {{ $av['bg'] ?? '#475569' }};">{{ $av['text'] }}</span>
                            </td>
                            {{-- Satu angka: iuran bulanan + uang pendaftaran. Uang pendaftaran
                                 hanya ditagih sekali, jadi angka ini tagihan bulan pertama.
                                 Rinciannya, tarif sepekan, & tangga harga bulan pertama muncul
                                 saat angkanya disinggahi kursor — judul kolomnya juga menyebut
                                 isi angka ini, jadi tak perlu penanda tersendiri di tiap baris. --}}
                            <td class="text-end">
                                @php
                                    $rincianBiaya = 'Iuran Rp '.number_format($class->class_fee, 0, ',', '.').' / bulan';
                                    if ($class->registration_fee > 0) {
                                        $rincianBiaya .= ' + uang pendaftaran Rp '.number_format($class->registration_fee, 0, ',', '.')
                                            .' (ditagih sekali saat murid mendaftar)';
                                    }
                                    if ($class->class_fee > 0) {
                                        $rincianBiaya .= '. Tarif sepekan ≈ Rp '.number_format($class->weeklyFee(), 0, ',', '.')
                                            .'. Iuran bulan pertama — '
                                            .collect(\App\Models\ClassRoom::START_WEEKS)
                                                ->map(fn ($w) => 'masuk minggu ke-'.$w.': Rp '.number_format($class->feeForStartWeek($w), 0, ',', '.'))
                                                ->implode(' · ');
                                    }
                                @endphp
                                <div class="cell-main text-nowrap" title="{{ $rincianBiaya }}">
                                    Rp {{ number_format($class->initialFee(), 0, ',', '.') }}
                                </div>
                            </td>
                            <td class="text-end col-actions">
                                <div class="d-inline-flex flex-nowrap gap-1">
                                    <form action="{{ route('classes.toggle-status', $class) }}" method="POST">
                                        @csrf @method('PATCH')
                                        @if($class->isClosed())
                                            <button class="btn btn-sm btn-outline-success" title="Buka kelas"><i class="bi bi-unlock"></i></button>
                                        @else
                                            <button class="btn btn-sm btn-outline-secondary" title="Tutup kelas"><i class="bi bi-lock"></i></button>
                                        @endif
                                    </form>
                                    <a href="{{ route('classes.edit', $class) }}" class="btn btn-sm btn-info text-white" title="Edit kelas"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('classes.destroy', $class) }}" method="POST" onsubmit="return confirm('Hapus kelas ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" title="Hapus kelas"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">
                            @if($search !== '' || $category !== '' || $status !== '' || $day !== '')
                                Tidak ada kelas yang cocok dengan filter.
                            @else
                                Belum ada kelas. Klik "Tambah kelas" untuk menambahkan.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $classes->links() }}
    </div>
</div>

{{-- Panel Manajemen tutor --}}
<div class="card" id="panelTutor" @if($tab !== 'tutor') style="display:none;" @endif>
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Daftar tutor <span class="badge bg-primary ms-1">{{ $tutors->count() }}</span></span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <input type="hidden" name="tab" value="tutor">
            <div class="input-group input-group-sm" style="width:190px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="tutor_search" value="{{ $tutorSearch }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari nama / HP tutor...">
            </div>
            <select name="tutor_status" class="form-select form-select-sm" style="width:190px;">
                <option value="">Semua status</option>
                <option value="full-time" @selected($tutorStatus === 'full-time')>Full-Time</option>
                <option value="part-time" @selected($tutorStatus === 'part-time')>Part-Time</option>
            </select>
            <select name="tutor_class" class="form-select form-select-sm" style="width:190px;">
                <option value="">Semua kelas diampu</option>
                @foreach($allClasses as $cls)
                    <option value="{{ $cls->id }}" @selected($tutorClassId == $cls->id)>{{ $cls->class_category }} ({{ $cls->class_code }})</option>
                @endforeach
            </select>
            @if($tutorSearch !== '' || $tutorStatus !== '' || $tutorClassId)
                <a href="{{ route('classes.index', ['tab' => 'tutor']) }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Nama tutor</th><th>No HP</th><th>Status</th><th>Murid diampu</th><th>Kelas diampu</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($tutors as $tutor)
                        @php $muridTutor = $tutor->activeStudents(); @endphp
                        <tr>
                            <td class="fw-bold">{{ $tutor->name }}</td>
                            <td>{{ $tutor->phone_number ?: '-' }}</td>
                            <td>
                                @if($tutor->status === 'full-time')
                                    <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: #15803D;">Full-Time</span>
                                @else
                                    <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: #0891B2;">Part-Time</span>
                                @endif
                            </td>
                            {{-- Jumlah murid, bukan jumlah kelas: yang ditanya admin adalah berapa
                                 anak yang dipegang seorang tutor, lalu siapa saja mereka. --}}
                            <td>
                                @if($muridTutor->isEmpty())
                                    <span class="badge bg-light text-muted border">0 murid</span>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold btn-tutor-students"
                                        data-bs-toggle="collapse" data-bs-target="#tutorStudents{{ $tutor->id }}"
                                        aria-expanded="false" aria-controls="tutorStudents{{ $tutor->id }}"
                                        title="Lihat daftar murid {{ $tutor->name }}">
                                        <i class="bi bi-people me-1"></i>{{ $muridTutor->count() }} murid
                                        <i class="bi bi-chevron-down ms-1 small"></i>
                                    </button>
                                @endif
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ $tutor->classes_count }} kelas</span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-info text-white btn-edit-tutor"
                                    data-action="{{ route('tutors.update', $tutor) }}"
                                    data-name="{{ $tutor->name }}"
                                    data-phone="{{ $tutor->phone_number }}"
                                    data-status="{{ $tutor->status }}"
                                    data-bs-toggle="modal" data-bs-target="#tutorEditModal">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if($tutor->classes_count > 0)
                                    <button type="button" class="btn btn-sm btn-danger btn-tutor-blocked"
                                        data-name="{{ $tutor->name }}" data-count="{{ $tutor->classes_count }}"
                                        data-bs-toggle="modal" data-bs-target="#tutorBlockedModal" title="Hapus tutor">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @else
                                    <form action="{{ route('tutors.destroy', $tutor) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus tutor {{ $tutor->name }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" title="Hapus tutor"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        {{-- Rincian murid, dikelompokkan per kelas: di situlah murid bertemu
                             tutornya, jadi daftar nama polos tidak menjawab "kapan & di kelas apa". --}}
                        @if($muridTutor->isNotEmpty())
                            <tr>
                                <td colspan="6" class="p-0 border-0">
                                    <div class="collapse" id="tutorStudents{{ $tutor->id }}">
                                        <div class="bg-light border-top px-3 py-3">
                                            <p class="small text-muted mb-3">
                                                <i class="bi bi-info-circle me-1"></i>
                                                {{ $tutor->name }} mengampu <strong>{{ $muridTutor->count() }} murid</strong> aktif
                                                di {{ $tutor->classes_count }} kelas. Murid yang ikut dua kelas tutor ini
                                                sekaligus tetap dihitung satu.
                                            </p>
                                            @foreach($tutor->classes as $cls)
                                                <div class="mb-3">
                                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-1 fw-semibold">{{ $cls->class_category }}</span>
                                                        <span class="small text-muted">{{ $cls->class_code }} &middot; {{ $cls->scheduleLabel() }}</span>
                                                        <span class="badge bg-white text-dark border">{{ $cls->students->count() }} / {{ $cls->capacity }} murid</span>
                                                        <a href="{{ route('students.index', ['class_id' => $cls->id]) }}" class="small ms-auto text-decoration-none">
                                                            Buka di daftar murid <i class="bi bi-box-arrow-up-right"></i>
                                                        </a>
                                                    </div>
                                                    @if($cls->students->isEmpty())
                                                        <p class="small text-muted fst-italic mb-0 ps-1">Belum ada murid aktif di kelas ini.</p>
                                                    @else
                                                        <div class="table-responsive bg-white border rounded">
                                                            <table class="table table-sm table-hover align-middle mb-0">
                                                                <thead>
                                                                    <tr class="small text-muted">
                                                                        <th>ID Murid</th><th>Nama</th><th>Usia</th><th>Wali</th><th>No HP</th><th class="text-end">Aksi</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($cls->students as $murid)
                                                                        <tr>
                                                                            <td class="text-muted small">{{ $murid->student_id }}</td>
                                                                            <td class="fw-semibold">{{ $murid->name }}</td>
                                                                            <td>{{ $murid->age ? $murid->age.' th' : '-' }}</td>
                                                                            <td>{{ $murid->parent_name ?: '-' }}</td>
                                                                            <td>{{ $murid->phone_number ?: '-' }}</td>
                                                                            <td class="text-end">
                                                                                <a href="{{ route('students.edit', $murid) }}" class="btn btn-sm btn-outline-primary" title="Buka data murid {{ $murid->name }}">
                                                                                    <i class="bi bi-person-lines-fill"></i>
                                                                                </a>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">
                            @if($tutorSearch !== '' || $tutorStatus !== '' || $tutorClassId)
                                Tidak ada tutor yang cocok dengan filter.
                            @else
                                Belum ada tutor. Klik "Tambah tutor" untuk menambahkan.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Panel Kalender jadwal -- kelas reguler, Holiday Class, & replacement.
     Isinya hanya dirender saat panel ini yang diminta; di tab lain kotaknya
     kosong dan tersembunyi. --}}
<div id="panelKalender" @if($tab !== 'kalender') style="display:none;" @endif>
    @if($tab === 'kalender')
        @include('schedules._calendar-panel', [
            'events' => $calendarEvents,
            'students' => $calendarStudents,
            'rosters' => $calendarRosters,
        ])
    @endif
</div>

<!-- Tutor Edit Modal -->
<div class="modal fade" id="tutorEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="tutorEditForm" action="#" method="POST">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">Edit tutor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama tutor</label><input type="text" name="name" id="editTutorName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">No HP</label><input type="text" name="phone_number" id="editTutorPhone" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" id="editTutorStatus" class="form-select" data-no-search>
                            <option value="full-time">Full-Time</option>
                            <option value="part-time">Part-Time</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan perubahan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: tutor tidak bisa dihapus (masih mengampu kelas) -->
<div class="modal fade" id="tutorBlockedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Tidak dapat menghapus tutor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Tutor <strong id="blockedTutorName">-</strong> tidak dapat dihapus karena masih mengampu <strong><span id="blockedTutorCount">0</span> kelas</strong>.</p>
                <p class="text-muted small mb-0">Pindahkan kelas tersebut ke tutor lain atau hapus kelasnya terlebih dahulu melalui panel Manajemen kelas, kemudian coba lagi.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>

<!-- Tutor Modal -->
<div class="modal fade" id="tutorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('tutors.store') }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Tambah tutor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama tutor</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">No HP</label><input type="text" name="phone_number" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select"><option value="full-time">Full-Time</option><option value="part-time">Part-Time</option></select>
                    </div>
                    <p class="small text-muted mb-0">Tutor terdaftar: {{ $tutors->count() }} orang.</p>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan tutor</button></div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ── Switch panel: Manajemen kelas <-> Manajemen tutor ──
    (function () {
        const panels = {
            kelas: document.getElementById('panelKelas'),
            tutor: document.getElementById('panelTutor'),
            kalender: document.getElementById('panelKalender'),
        };
        const toggleKelas = document.getElementById('toggleKelas');
        const toggleTutor = document.getElementById('toggleTutor');
        const toggleKalender = document.getElementById('toggleKalender');
        const btnAddClass = document.getElementById('btnAddClass');
        const btnAddTutor = document.getElementById('btnAddTutor');
        const btnReplacement = document.getElementById('btnReplacement');

        function applyPanel(tab) {
            Object.keys(panels).forEach(function (nama) {
                if (panels[nama]) panels[nama].style.display = nama === tab ? '' : 'none';
            });

            // Tombol aksi mengikuti panel: tiap panel punya satu perbuatan utama.
            btnAddClass.style.display = tab === 'kelas' ? '' : 'none';
            btnAddTutor.style.display = tab === 'tutor' ? '' : 'none';
            if (btnReplacement) btnReplacement.style.display = tab === 'kalender' ? '' : 'none';
            if (toggleKalender) toggleKalender.classList.toggle('active', tab === 'kalender');

            // Simpan tab di URL agar bertahan saat reload / setelah submit filter.
            const url = new URL(location);
            if (tab === 'kelas') { url.searchParams.delete('tab'); } else { url.searchParams.set('tab', tab); }
            history.replaceState(null, '', url);

            // FullCalendar mengukur tinggi & lebarnya saat render; di dalam panel
            // yang tersembunyi hasilnya nol, dan kalender tampil sebagai garis
            // tipis sampai jendela diubah ukurannya.
            if (tab === 'kalender' && window.jadwalCalendar) {
                window.jadwalCalendar.updateSize();
            }
        }

        toggleKelas.addEventListener('change', function () { applyPanel('kelas'); });
        toggleTutor.addEventListener('change', function () { applyPanel('tutor'); });
        applyPanel(@json($tab));
    })();

    // ── Isi modal edit tutor ──
    (function () {
        const form = document.getElementById('tutorEditForm');
        document.querySelectorAll('.btn-edit-tutor').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.action = btn.dataset.action;
                document.getElementById('editTutorName').value = btn.dataset.name;
                document.getElementById('editTutorPhone').value = btn.dataset.phone || '';
                document.getElementById('editTutorStatus').value = btn.dataset.status;
            });
        });
    })();

    // ── Chevron tombol "N murid" mengikuti buka/tutup rincian ──
    (function () {
        document.querySelectorAll('.btn-tutor-students').forEach(function (btn) {
            const panel = document.querySelector(btn.dataset.bsTarget);
            if (!panel) return;
            const chevron = btn.querySelector('.bi-chevron-down, .bi-chevron-up');
            panel.addEventListener('show.bs.collapse', function () {
                chevron.classList.replace('bi-chevron-down', 'bi-chevron-up');
            });
            panel.addEventListener('hide.bs.collapse', function () {
                chevron.classList.replace('bi-chevron-up', 'bi-chevron-down');
            });
        });
    })();

    // ── Modal peringatan: tutor masih mengampu kelas ──
    (function () {
        document.querySelectorAll('.btn-tutor-blocked').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('blockedTutorName').textContent = btn.dataset.name;
                document.getElementById('blockedTutorCount').textContent = btn.dataset.count;
            });
        });
    })();
</script>
@endpush
