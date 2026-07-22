@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Manajemen Kelas</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tutorModal"><i class="bi bi-person-plus"></i> Tambah Tutor</button>
        <a href="{{ route('classes.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Tambah Kelas</a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Kelas</span>
        <form method="GET" data-live class="d-flex" style="max-width:300px;">
            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm me-2" placeholder="Cari kelas...">
            <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
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
