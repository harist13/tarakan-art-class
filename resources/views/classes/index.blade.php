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
                <option value="">Semua Tipe</option>
                <option value="preschool" @selected($category === 'preschool')>Preschool</option>
                <option value="coloring" @selected($category === 'coloring')>Coloring</option>
                <option value="drawing" @selected($category === 'drawing')>Drawing</option>
            </select>
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua Status</option>
                <option value="tersedia" @selected($status === 'tersedia')>Tersedia</option>
                <option value="penuh" @selected($status === 'penuh')>Penuh</option>
                <option value="ditutup" @selected($status === 'ditutup')>Ditutup</option>
            </select>
            <div class="input-group input-group-sm" style="width:240px;">
                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                <input type="text" id="jadwalRange" data-no-live class="form-control py-2" placeholder="Semua tanggal jadwal" autocomplete="off" readonly>
                <button type="button" id="jadwalClear" class="btn btn-outline-secondary" title="Hapus rentang" @if($dateFrom === '' && $dateTo === '') style="display:none" @endif><i class="bi bi-x-lg"></i></button>
                <input type="hidden" name="date_from" id="dateFrom" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" id="dateTo" value="{{ $dateTo }}">
            </div>
            @if($search !== '' || $category !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '')
                <a href="{{ route('classes.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Kode</th><th>Nama Kelas</th><th>Kategori</th><th>Tutor</th><th>Kapasitas</th><th>Ketersediaan</th><th>Jadwal</th><th>Biaya</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($classes as $class)
                        <tr>
                            <td class="fw-bold">{{ $class->class_code }}</td>
                            <td>{{ $class->class_name }}</td>
                            <td><span class="badge bg-light text-dark border text-capitalize">{{ $class->class_category }}</span></td>
                            <td>{{ $class->tutor->name ?? '-' }}</td>
                            <td>{{ $class->enrolledCount() }} / {{ $class->capacity }}</td>
                            <td>
                                @php $av = $class->availability(); @endphp
                                <span class="badge bg-{{ $av['color'] }}">{{ $av['text'] }}</span>
                            </td>
                            <td>{{ $class->schedule_date->format('d M Y') }}<br><small class="text-muted">{{ \Illuminate\Support\Str::of($class->schedule_time)->substr(0,5) }}</small></td>
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
                        <tr><td colspan="9" class="text-center text-muted">Belum ada kelas.</td></tr>
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
                <option value="active" @selected($tutorStatus === 'active')>Aktif</option>
                <option value="inactive" @selected($tutorStatus === 'inactive')>Nonaktif</option>
            </select>
            <select name="tutor_class" class="form-select form-select-sm" style="width:190px;">
                <option value="">Semua Kelas Diampu</option>
                @foreach($allClasses as $cls)
                    <option value="{{ $cls->id }}" @selected($tutorClassId == $cls->id)>{{ $cls->class_name }} ({{ $cls->class_code }})</option>
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
                    <tr><th>Nama Tutor</th><th>No HP</th><th>Status</th><th>Kelas Diampu</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($tutors as $tutor)
                        <tr>
                            <td class="fw-bold">{{ $tutor->name }}</td>
                            <td>{{ $tutor->phone_number ?: '-' }}</td>
                            <td><span class="badge bg-{{ $tutor->status === 'active' ? 'success' : 'secondary' }}">{{ $tutor->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span></td>
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
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">
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
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
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
                        <select name="status" class="form-select"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
                    </div>
                    <p class="small text-muted mb-0">Tutor terdaftar: {{ $tutors->count() }} orang.</p>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan Tutor</button></div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    /* Selaraskan popup flatpickr dengan tema (terutama dark mode). */
    [data-bs-theme="dark"] .flatpickr-calendar {
        background: var(--surface); color: var(--text); box-shadow: 0 6px 24px rgba(0,0,0,0.5);
    }
    [data-bs-theme="dark"] .flatpickr-calendar .flatpickr-months,
    [data-bs-theme="dark"] .flatpickr-calendar .flatpickr-weekday { color: var(--text); fill: var(--text); }
    [data-bs-theme="dark"] .flatpickr-day { color: var(--text); }
    [data-bs-theme="dark"] .flatpickr-day.prevMonthDay,
    [data-bs-theme="dark"] .flatpickr-day.nextMonthDay { color: var(--text-muted); }
    [data-bs-theme="dark"] .flatpickr-day:hover { background: var(--surface-2); border-color: var(--surface-2); }
    [data-bs-theme="dark"] .flatpickr-day.inRange { background: rgba(14,165,233,0.18); border-color: transparent; box-shadow: none; }
    .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange { background: var(--primary-color); border-color: var(--primary-color); }
    #jadwalRange { background-color: var(--surface); cursor: pointer; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    (function () {
        const input = document.getElementById('jadwalRange');
        if (!input || !window.flatpickr) return;

        const form = input.closest('form');
        const hFrom = document.getElementById('dateFrom');
        const hTo = document.getElementById('dateTo');
        const clearBtn = document.getElementById('jadwalClear');

        // Preload rentang dari nilai filter aktif (kirim sebagai Date agar tak salah parse).
        const preset = [];
        if (hFrom.value) preset.push(new Date(hFrom.value + 'T00:00:00'));
        if (hTo.value) preset.push(new Date(hTo.value + 'T00:00:00'));

        function submitForm() {
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }

        const fp = flatpickr(input, {
            mode: 'range',
            dateFormat: 'd/m/Y',        // format tampilan di field
            rangeSeparator: ' s/d ',
            disableMobile: true,        // pakai kalender flatpickr (range) juga di mobile
            defaultDate: preset.length ? preset : null,
            onClose: function (dates) {
                let from = '', to = '';
                if (dates.length === 2) {
                    from = fp.formatDate(dates[0], 'Y-m-d');
                    to = fp.formatDate(dates[1], 'Y-m-d');
                } else if (dates.length === 1) {
                    from = to = fp.formatDate(dates[0], 'Y-m-d');
                }
                if (from === hFrom.value && to === hTo.value) return; // tak berubah
                hFrom.value = from;
                hTo.value = to;
                submitForm();
            },
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (hFrom.value === '' && hTo.value === '') return;
                fp.clear();
                hFrom.value = '';
                hTo.value = '';
                submitForm();
            });
        }
    })();

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
