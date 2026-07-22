@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Raport Siswa</h1>
    <a href="{{ route('reports.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Buat Raport</a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Raport</span>
        <form method="GET" data-live class="d-flex" style="max-width:320px;">
            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm me-2" placeholder="Cari murid / credential key...">
            <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Credential Key</th><th>Murid</th><th>Periode</th><th>Nilai</th><th>Dibuat oleh</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td><code>{{ $report->credential_key }}</code></td>
                            <td class="fw-bold">{{ $report->student->name ?? '-' }}</td>
                            <td class="small">{{ $report->period_start->format('d M Y') }} — {{ $report->period_end->format('d M Y') }}</td>
                            <td><span class="badge bg-{{ $report->achievement_score >= 75 ? 'success' : ($report->achievement_score >= 50 ? 'warning' : 'danger') }}">{{ $report->achievement_score }}</span></td>
                            <td class="small">{{ $report->creator->full_name ?? '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('reports.show', $report) }}" class="btn btn-sm btn-secondary"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('reports.destroy', $report) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus raport ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Belum ada raport.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $reports->links() }}
    </div>
</div>
@endsection
