@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Absensi Kelas</h1>
    <div class="d-flex gap-2">
        @include('partials.export-buttons', ['route' => 'export.attendances'])
        <a href="{{ route('attendances.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Input Absensi</a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Rekap Kehadiran</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:190px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari murid...">
            </div>
            <select name="class_id" class="form-select form-select-sm" style="width:190px;" aria-label="Filter kelas">
                <option value="">Semua Kelas</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected($classId == $class->id)>{{ $class->class_category }}</option>
                @endforeach
            </select>
            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm py-2" style="width:170px;" aria-label="Filter tanggal">
            @if($search !== '' || $classId || $date !== '')
                <a href="{{ route('attendances.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Tanggal</th><th>Murid</th><th>Kelas</th><th>Status</th><th>Catatan</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($attendances as $att)
                        <tr>
                            <td>{{ $att->attendance_date->format('d M Y') }}</td>
                            <td class="fw-bold">{{ $att->student->name ?? '-' }}</td>
                            <td>{{ $att->classRoom->class_category ?? '-' }}</td>
                            <td>
                                @php
                                    $attStyles = [
                                        'present' => ['bg' => '#15803D', 'label' => 'Hadir'],
                                        'absent'  => ['bg' => '#DC2626', 'label' => 'Alfa'],
                                        'permit'  => ['bg' => 'rgba(245, 136, 12, 1)', 'label' => 'Izin'],
                                    ];
                                    $st = $attStyles[$att->status] ?? ['bg' => '#475569', 'label' => ucfirst($att->status)];
                                @endphp
                                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: {{ $st['bg'] }};">
                                    {{ $st['label'] }}
                                </span>
                            </td>
                            <td class="small">{{ $att->notes ?: '-' }}</td>
                            <td class="text-end">
                                <form action="{{ route('attendances.destroy', $att) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data absensi ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Belum ada data absensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $attendances->links() }}
    </div>
</div>
@endsection
