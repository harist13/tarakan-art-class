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
        <form method="GET" data-live class="d-flex gap-2" style="max-width:420px;">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
            </select>
            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Cari nama / ID...">
            <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>ID Murid</th><th>Nama Murid</th><th>Kelas</th><th>Nama Wali</th><th>No HP</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td class="fw-bold">{{ $student->student_id }}</td>
                            <td>{{ $student->name }}</td>
                            <td>
                                @forelse($student->classes as $class)
                                    <span class="badge bg-light text-dark border">{{ $class->class_name }}</span>
                                @empty
                                    <span class="text-muted small">Belum ada kelas</span>
                                @endforelse
                            </td>
                            <td>{{ $student->parent_name }}</td>
                            <td>{{ $student->phone_number }}</td>
                            <td><span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($student->status) }}</span></td>
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
                        <tr><td colspan="7" class="text-center text-muted">Tidak ada data murid.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
</div>
@endsection
