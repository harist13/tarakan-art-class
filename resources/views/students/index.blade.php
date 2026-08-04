@extends('layouts.app')

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
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>ID Murid</th><th>Nama Murid</th><th>Usia</th><th>Kelas</th><th>Nama Wali</th><th>No HP</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td class="fw-bold">{{ $student->student_id }}</td>
                            <td>{{ $student->name }}</td>
                            <td>
                                {{ $student->age !== null ? $student->age.' th' : '-' }}
                                @if($student->has_manual_age)
                                    <i class="bi bi-pencil-fill text-muted small" title="Usia diisi manual (hitungan dari tanggal lahir: {{ $student->calculated_age }} th)"></i>
                                @endif
                            </td>
                            <td>
                                @forelse($student->classes as $class)
                                    <span class="badge bg-light text-dark border">{{ $class->class_name }}</span>
                                @empty
                                    <span class="text-muted small">Belum ada kelas</span>
                                @endforelse
                            </td>
                            <td>{{ $student->parent_name }}</td>
                            <td>{{ $student->phone_number }}</td>
                            <td>
                                <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($student->status) }}</span>
                                {{-- Murid belum lunas tidak muncul di absensi, raport, & replacement. --}}
                                @if(! $student->isPaid())
                                    <span class="badge bg-warning text-dark d-block mt-1"
                                          title="Murid {{ $student->paymentBlockReason() }} — tidak muncul di absensi, raport, & replacement class.">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $student->paymentBadgeLabel() }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('students.edit', $student) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                @if(auth()->user()->isSuperAdmin())
                                <form action="{{ route('students.destroy', $student) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus permanen murid ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">Tidak ada data murid.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
</div>
@endsection
