@extends('layouts.app')

@push('styles')
<style>
    /* Tabel di halaman ini punya tujuh kolom. Dengan padding 1rem bawaan, ruang
       kosongnya sendiri sudah memakan ratusan piksel dan memaksa tabel bergulir
       pada layar yang sebenarnya cukup lebar. */
    #panelKelas .table th,
    #panelKelas .table td,
    #panelTutor .table th,
    #panelTutor .table td {
        padding: 0.75rem 0.6rem;
    }

    /* Kolom sempit tidak boleh memotong isinya sendiri: nama tutor & kategori
       boleh turun baris, sedangkan angka uang & tanggal tidak. */
    #panelKelas .table td,
    #panelTutor .table td {
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
    <h1 class="h3 mb-0 text-gray-800 fw-bold" id="pageTitle">Manajemen Kelas & Tutor</h1>
    <div class="d-flex gap-2">
        <button id="btnAddTutor" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tutorModal" style="display:none;"><i class="bi bi-person-plus"></i> Tambah Tutor</button>
        <button type="button" id="btnReplacement" class="btn btn-sm btn-primary shadow-sm" style="display:none;"><i class="bi bi-pencil-square"></i> Ubah</button>
        <a id="btnAddClass" href="{{ route('classes.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Tambah Kelas</a>
    </div>
</div>

{{-- Switch panel: Kelas <-> Tutor <-> Kalender.

     Dua yang pertama berpindah di sisi klien karena datanya sudah dirender.
     Kalender berupa tautan biasa: eventnya ratusan dan hanya disusun saat panel
     itu memang diminta -- lihat ClassRoomController::index. --}}
<div class="btn-group mb-4 shadow-sm flex-wrap" role="group" aria-label="Pilih panel">
    <input type="radio" class="btn-check" name="panelToggle" id="toggleKelas" autocomplete="off" @checked($tab === 'kelas')>
    <label class="btn btn-outline-primary" for="toggleKelas"><i class="bi bi-easel2 me-1"></i> Manajemen Kelas</label>
    <input type="radio" class="btn-check" name="panelToggle" id="toggleTutor" autocomplete="off" @checked($tab === 'tutor')>
    <label class="btn btn-outline-primary" for="toggleTutor"><i class="bi bi-person-video3 me-1"></i> Manajemen Tutor</label>
    <a href="{{ route('classes.index', ['tab' => 'kalender']) }}" id="toggleKalender"
        class="btn btn-outline-primary @if($tab === 'kalender') active @endif">
        <i class="bi bi-calendar3-week me-1"></i> Kalender Jadwal
    </a>
</div>

<div class="card" id="panelKelas" @if($tab === 'tutor') style="display:none;" @endif>
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Daftar Kelas</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:180px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari kelas...">
            </div>
            <select name="category" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" @selected($category === $cat)>{{ ucfirst($cat) }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua Status</option>
                <option value="tersedia" @selected($status === 'tersedia')>Tersedia</option>
                <option value="penuh" @selected($status === 'penuh')>Penuh</option>
                <option value="tanpa-tutor" @selected($status === 'tanpa-tutor')>Tutor Kosong</option>
                <option value="ditutup" @selected($status === 'ditutup')>Ditutup</option>
            </select>
            <select name="day" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua Hari</option>
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
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Kelas</th>
                        <th>Tutor</th>
                        <th>Jadwal</th>
                        <th>Kapasitas</th>
                        <th>Ketersediaan</th>
                        <th class="text-end">Biaya</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classes as $class)
                        @php
                            $av = $class->availability();
                            $terisi = $class->enrolledCount();
                            $persen = $class->capacity > 0 ? min(100, round($terisi / $class->capacity * 100)) : 0;
                            $warnaIsi = $persen >= 100 ? 'bg-danger' : ($persen >= 75 ? 'bg-warning' : 'bg-success');
                        @endphp
                        <tr>
                            {{-- Kode & kategori disatukan: keduanya menjawab "kelas yang mana",
                                 dan kolom terpisah hanya melebarkan tabel tanpa menambah info. --}}
                            <td>
                                <div class="fw-bold text-capitalize">{{ $class->class_category }}</div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="small text-muted">{{ $class->class_code }}</span>
                                    @php $warnaTipe = $class->isTrial() ? 'warning' : 'primary'; @endphp
                                    <span class="badge bg-{{ $warnaTipe }}-subtle text-{{ $warnaTipe }}-emphasis border border-{{ $warnaTipe }}-subtle">{{ $class->typeLabel() }}</span>
                                </div>
                            </td>
                            <td>{{ $class->tutor->name ?? '-' }}</td>
                            <td>
                                <div>{{ $class->scheduleLabel() }}</div>
                                <small class="text-muted d-block">
                                    @if(! $class->is_recurring)
                                        <i class="bi bi-calendar-x me-1"></i>Sekali jalan
                                    @elseif($next = $class->nextOccurrence())
                                        <i class="bi bi-arrow-repeat me-1"></i>Sesi berikutnya {{ $next->format('d M Y') }}
                                    @else
                                        Belum ada sesi mendatang
                                    @endif
                                </small>
                            </td>
                            {{-- Angka saja sulit dibaca sekilas; bar tipis membuat kelas yang
                                 hampir penuh langsung terlihat tanpa membandingkan dua angka. --}}
                            <td>
                                <div class="fw-semibold">{{ $terisi }} <span class="text-muted fw-normal">/ {{ $class->capacity }}</span></div>
                                <div class="progress mt-1" style="height:5px; min-width:56px;" role="progressbar"
                                    aria-label="Keterisian kelas {{ $class->class_code }}" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar {{ $warnaIsi }}" style="width: {{ $persen }}%"></div>
                                </div>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: {{ $av['bg'] ?? '#475569' }};">{{ $av['text'] }}</span>
                            </td>
                            {{-- Uang pendaftaran hanya ditagih sekali, jadi ditulis sebagai
                                 tambahan di bawah iuran, bukan dijumlah diam-diam. --}}
                            <td class="text-end">
                                <div class="fw-semibold text-nowrap">Rp {{ number_format($class->class_fee, 0, ',', '.') }}</div>
                                @if($class->registration_fee > 0)
                                    <small class="text-muted text-nowrap d-block" title="Uang pendaftaran, ditagih sekali saat murid mendaftar">
                                        + Rp {{ number_format($class->registration_fee, 0, ',', '.') }} daftar
                                    </small>
                                @endif
                                {{-- Tarif sepekan: dasar harga bulan pertama murid yang masuk
                                     di pertengahan bulan. Tangga lengkapnya di tooltip —
                                     empat angka di baris tabel akan menenggelamkan iuran pokoknya. --}}
                                @if($class->class_fee > 0)
                                    @php
                                        $rincian = collect(\App\Models\ClassRoom::START_WEEKS)
                                            ->map(fn ($w) => 'Masuk minggu ke-'.$w.': Rp '.number_format($class->feeForStartWeek($w), 0, ',', '.'))
                                            ->implode(' · ');
                                    @endphp
                                    <small class="text-muted text-nowrap d-block" title="Harga bulan pertama — {{ $rincian }}">
                                        ≈ Rp {{ number_format($class->weeklyFee(), 0, ',', '.') }} / pekan
                                    </small>
                                @endif
                            </td>
                            <td class="text-end">
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
                        <tr><td colspan="7" class="text-center text-muted py-4">
                            @if($search !== '' || $category !== '' || $status !== '' || $day !== '')
                                Tidak ada kelas yang cocok dengan filter.
                            @else
                                Belum ada kelas. Klik "Tambah Kelas" untuk menambahkan.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $classes->links() }}
    </div>
</div>

{{-- Panel Manajemen Tutor --}}
<div class="card" id="panelTutor" @if($tab !== 'tutor') style="display:none;" @endif>
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Daftar Tutor <span class="badge bg-primary ms-1">{{ $tutors->count() }}</span></span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <input type="hidden" name="tab" value="tutor">
            <div class="input-group input-group-sm" style="width:190px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="tutor_search" value="{{ $tutorSearch }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari nama / HP tutor...">
            </div>
            <select name="tutor_status" class="form-select form-select-sm" style="width:190px;">
                <option value="">Semua Status</option>
                <option value="full-time" @selected($tutorStatus === 'full-time')>Full-Time</option>
                <option value="part-time" @selected($tutorStatus === 'part-time')>Part-Time</option>
            </select>
            <select name="tutor_class" class="form-select form-select-sm" style="width:190px;">
                <option value="">Semua Kelas Diampu</option>
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
                    <tr><th>Nama Tutor</th><th>No HP</th><th>Status</th><th>Murid Diampu</th><th>Kelas Diampu</th><th class="text-end">Aksi</th></tr>
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
                                Belum ada tutor. Klik "Tambah Tutor" untuk menambahkan.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Panel Kalender Jadwal -- kelas reguler, Holiday Class, & replacement.
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
                <div class="modal-header"><h5 class="modal-title">Edit Tutor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama Tutor</label><input type="text" name="name" id="editTutorName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">No HP</label><input type="text" name="phone_number" id="editTutorPhone" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" id="editTutorStatus" class="form-select" data-no-search>
                            <option value="full-time">Full-Time</option>
                            <option value="part-time">Part-Time</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: tutor tidak bisa dihapus (masih mengampu kelas) -->
<div class="modal fade" id="tutorBlockedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Tidak Dapat Menghapus Tutor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Tutor <strong id="blockedTutorName">-</strong> tidak dapat dihapus karena masih mengampu <strong><span id="blockedTutorCount">0</span> kelas</strong>.</p>
                <p class="text-muted small mb-0">Pindahkan kelas tersebut ke tutor lain atau hapus kelasnya terlebih dahulu melalui panel Manajemen Kelas, kemudian coba lagi.</p>
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
                <div class="modal-header"><h5 class="modal-title">Tambah Tutor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama Tutor</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">No HP</label><input type="text" name="phone_number" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select"><option value="full-time">Full-Time</option><option value="part-time">Part-Time</option></select>
                    </div>
                    <p class="small text-muted mb-0">Tutor terdaftar: {{ $tutors->count() }} orang.</p>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan Tutor</button></div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ── Switch panel: Manajemen Kelas <-> Manajemen Tutor ──
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
