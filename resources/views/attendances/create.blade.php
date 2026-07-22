@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Input Absensi</h1>
    <a href="{{ route('attendances.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card">
    <div class="card-header">Pilih Kelas & Tanggal</div>
    <div class="card-body">
        <form method="GET" action="{{ route('attendances.create') }}" class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Kelas</label>
                <select name="class_id" class="form-select" onchange="this.form.submit()" required>
                    <option value="">— Pilih Kelas —</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass && $selectedClass->id === $class->id)>{{ $class->class_name }} ({{ $class->class_code }})</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

@if($selectedClass)
    <div class="card">
        <div class="card-header">Daftar Murid — {{ $selectedClass->class_name }}</div>
        <div class="card-body">
            @if($selectedClass->students->isEmpty())
                <p class="text-muted mb-0">Belum ada murid aktif di kelas ini.</p>
            @else
            <form action="{{ route('attendances.store') }}" method="POST">
                @csrf
                <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">
                <div class="mb-3" style="max-width:260px;">
                    <label class="form-label">Tanggal Absensi</label>
                    <input type="date" name="attendance_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Murid</th><th style="width:220px;">Status</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @foreach($selectedClass->students as $i => $student)
                                <tr>
                                    <td class="fw-bold">{{ $student->name }} <span class="text-muted small">({{ $student->student_id }})</span>
                                        <input type="hidden" name="records[{{ $i }}][student_id]" value="{{ $student->id }}">
                                    </td>
                                    <td>
                                        <select name="records[{{ $i }}][status]" class="form-select form-select-sm">
                                            <option value="present">Hadir</option>
                                            <option value="absent">Absen</option>
                                            <option value="permit">Izin</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="records[{{ $i }}][notes]" class="form-control form-control-sm" placeholder="opsional"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Absensi</button>
            </form>
            @endif
        </div>
    </div>
@endif
@endsection
