@extends('layouts.app')

@push('styles')
<style>
    /* Tabel murid: 7 kolom cukup padat, jadi padding dirapatkan sedikit
       dan kolom aksi dibuat menempel di kanan saat tabel di-scroll. */
    .students-table thead th,
    .students-table tbody td { padding: 0.85rem 1rem; }
    .students-table th { white-space: nowrap; }
    .students-table .cell-main { font-weight: 600; line-height: 1.25; }
    .students-table .cell-sub { font-size: 0.8rem; color: var(--text-muted); line-height: 1.25; }
    .students-table .col-actions { position: sticky; right: 0; background-color: var(--surface); }
    .students-table thead .col-actions { background-color: var(--surface-2); }
    .students-table .col-actions::before {
        content: ''; position: absolute; top: 0; bottom: 0; left: 0;
        border-left: 1px solid var(--border);
    }
    .students-table tbody tr:hover td.col-actions { background-color: var(--surface-2); }
</style>
@endpush

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Data Murid & Wali</h1>
    <div class="d-flex gap-2">
        @include('partials.export-buttons', ['route' => 'export.students'])
        <a href="{{ route('students.create') }}" class="btn btn-sm btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Tambah Murid
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Daftar Murid</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:180px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari nama / ID...">
            </div>
            <select name="class_id" class="form-select form-select-sm" style="width:160px;">
                <option value="">Semua Kelas</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected($classId == $class->id)>{{ $class->class_name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua Status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
            </select>
            @if($search !== '' || $classId || $status !== '')
                <a href="{{ route('students.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle students-table">
                <thead>
                    <tr>
                        <th>Murid</th>
                        <th>Usia</th>
                        <th>Kelas</th>
                        <th>Wali</th>
                        <th>Bergabung</th>
                        <th>Status</th>
                        <th class="text-end col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td>
                                <div class="cell-main">{{ $student->name }}</div>
                                <div class="cell-sub">{{ $student->student_id }}</div>
                            </td>
                            <td class="text-nowrap">
                                {{ $student->age !== null ? $student->age.' th' : '-' }}
                                @if($student->has_manual_age)
                                    <i class="bi bi-pencil-fill text-muted small" title="Usia diisi manual (hitungan dari tanggal lahir: {{ $student->calculated_age }} th)"></i>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($student->classes as $class)
                                        <span class="badge bg-light text-dark border">{{ $class->class_name }}</span>
                                    @empty
                                        <span class="text-muted small">Belum ada kelas</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="cell-main">{{ $student->parent_name }}</div>
                                <div class="cell-sub">{{ $student->phone_number }}</div>
                            </td>
                            <td class="text-nowrap">{{ optional($student->join_date)->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                <div class="d-flex flex-column align-items-start gap-1">
                                    <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($student->status) }}</span>
                                    {{-- Penangguhan sistem: murid keluar dari daftar kelas ke depan, datanya tetap utuh. --}}
                                    @if($student->isSuspended())
                                        <span class="badge bg-danger"
                                              title="{{ $student->suspended_reason }} — tidak masuk daftar kelas berikutnya sampai tunggakan lunas.">
                                            <i class="bi bi-pause-circle-fill me-1"></i>Ditangguhkan
                                        </span>
                                    @endif
                                    {{-- Penanda tagihan. Absensi & raport tetap jalan; yang ditahan hanya
                                         kelas pengganti, pindah kelas, dan akses raport orang tua. --}}
                                    @if($label = $student->paymentBadgeLabel())
                                        <span class="badge bg-warning text-dark"
                                              title="{{ $student->hasArrears()
                                                  ? 'Murid '.$student->paymentBlockReason().' — kelas pengganti & akses raport orang tua ditahan.'
                                                  : 'Belum ada invoice yang dilunasi. Absensi tetap bisa dicatat.' }}">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $label }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end text-nowrap col-actions">
                                <a href="{{ route('students.edit', $student) }}" class="btn btn-sm btn-info text-white" title="Edit murid"><i class="bi bi-pencil"></i></a>
                                @if(auth()->user()->isSuperAdmin())
                                <form action="{{ route('students.destroy', $student) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus permanen murid ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="Hapus murid"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Tidak ada data murid.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
</div>
@endsection
