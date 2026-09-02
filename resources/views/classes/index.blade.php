@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold" id="pageTitle">Manajemen Kelas & Tutor</h1>
    <div class="d-flex gap-2">
        <button id="btnAddTutor" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tutorModal" style="display:none;"><i class="bi bi-person-plus"></i> Tambah Tutor</button>
        <a id="btnAddClass" href="{{ route('classes.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Tambah Kelas</a>
    </div>
</div>

{{-- Switch toggle: Manajemen Kelas <-> Manajemen Tutor --}}
<div class="btn-group mb-4 shadow-sm" role="group" aria-label="Pilih panel">
    <input type="radio" class="btn-check" name="panelToggle" id="toggleKelas" autocomplete="off" @checked($tab === 'kelas')>
    <label class="btn btn-outline-primary" for="toggleKelas"><i class="bi bi-easel2 me-1"></i> Manajemen Kelas</label>
    <input type="radio" class="btn-check" name="panelToggle" id="toggleTutor" autocomplete="off" @checked($tab === 'tutor')>
    <label class="btn btn-outline-primary" for="toggleTutor"><i class="bi bi-person-video3 me-1"></i> Manajemen Tutor</label>
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
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Kode</th><th>Kategori</th><th>Tutor</th><th>Kapasitas</th><th>Ketersediaan</th><th>Jadwal</th><th>Biaya</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($classes as $class)
                        <tr>
                            <td class="fw-bold">{{ $class->class_code }}</td>
                            <td><span class="badge bg-light text-dark border rounded-pill px-2 py-1 fw-semibold">{{ $class->class_category }}</span></td>
                            <td>{{ $class->tutor->name ?? '-' }}</td>
                            <td>{{ $class->enrolledCount() }} / {{ $class->capacity }}</td>
                            <td>
                                @php $av = $class->availability(); @endphp
                                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: {{ $av['bg'] ?? '#475569' }};">{{ $av['text'] }}</span>
                            </td>
                            <td>
                                {{ $class->scheduleLabel() }}
                                <br><small class="text-muted">
                                    @if(! $class->is_recurring)
                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-1 fw-semibold">Sekali jalan</span>
                                    @elseif($next = $class->nextOccurrence())
                                        Sesi berikutnya {{ $next->format('d M Y') }}
                                    @else
                                        Belum ada sesi mendatang
                                    @endif
                                </small>
                            </td>
                            <td>Rp {{ number_format($class->class_fee, 0, ',', '.') }}</td>
                            <td class="text-end">
                                <form action="{{ route('classes.toggle-status', $class) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    @if($class->isClosed())
                                        <button class="btn btn-sm btn-outline-success" title="Buka kelas"><i class="bi bi-unlock"></i></button>
                                    @else
                                        <button class="btn btn-sm btn-outline-secondary" title="Tutup kelas"><i class="bi bi-lock"></i></button>
                                    @endif
                                </form>
                                <a href="{{ route('classes.edit', $class) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('classes.destroy', $class) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kelas ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">Belum ada kelas.</td></tr>
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
        const panelKelas = document.getElementById('panelKelas');
        const panelTutor = document.getElementById('panelTutor');
        const toggleKelas = document.getElementById('toggleKelas');
        const toggleTutor = document.getElementById('toggleTutor');
        const btnAddClass = document.getElementById('btnAddClass');
        const btnAddTutor = document.getElementById('btnAddTutor');

        function applyPanel(tab) {
            const tutorActive = tab === 'tutor';
            panelKelas.style.display = tutorActive ? 'none' : '';
            panelTutor.style.display = tutorActive ? '' : 'none';
            btnAddClass.style.display = tutorActive ? 'none' : '';
            btnAddTutor.style.display = tutorActive ? '' : 'none';
            // Simpan tab di URL agar bertahan saat reload / setelah submit filter.
            const url = new URL(location);
            if (tutorActive) { url.searchParams.set('tab', 'tutor'); } else { url.searchParams.delete('tab'); }
            history.replaceState(null, '', url);
        }

        toggleKelas.addEventListener('change', function () { applyPanel('kelas'); });
        toggleTutor.addEventListener('change', function () { applyPanel('tutor'); });
        applyPanel(toggleTutor.checked ? 'tutor' : 'kelas');
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
