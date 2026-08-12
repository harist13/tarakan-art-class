@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Input Absensi</h1>
    <a href="{{ route('attendances.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card">
    <div class="card-header">Pilih Kelas</div>
    <div class="card-body">
        <form method="GET" action="{{ route('attendances.create') }}">
            <div style="max-width:420px;">
                <label class="form-label" for="class_id">Kelas</label>
                <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()" required>
                    <option value="">— Pilih Kelas —</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass && $selectedClass->id === $class->id)>{{ $class->class_name }} ({{ $class->class_code }})</option>
                    @endforeach
                </select>
                <div class="form-text">Daftar murid muncul otomatis setelah kelas dipilih.</div>
            </div>
        </form>
    </div>
</div>

@if($selectedClass)
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Daftar Murid — {{ $selectedClass->class_name }}</span>
            <span class="badge bg-primary-subtle text-primary">{{ $students->count() }} murid bisa diabsen</span>
        </div>
        <div class="card-body">
            {{-- Murid yang ditangguhkan sistem karena tunggakan lewat masa toleransi.
                 Kehadiran murid menunggak yang belum ditangguhkan TETAP bisa dicatat. --}}
            @if($suspendedStudents->isNotEmpty())
                <div class="alert alert-warning">
                    <div class="fw-bold mb-1"><i class="bi bi-pause-circle-fill me-1"></i>{{ $suspendedStudents->count() }} murid sedang ditangguhkan karena tunggakan</div>
                    <ul class="small mb-2 ps-3">
                        @foreach($suspendedStudents as $blocked)
                            <li>{{ $blocked->name }} ({{ $blocked->student_id }}) — {{ $blocked->suspended_reason ?: $blocked->paymentBlockReason() }}</li>
                        @endforeach
                    </ul>
                    <div class="small mb-0">Lunasi tunggakannya di <a href="{{ route('payments.index') }}" class="alert-link">menu Pembayaran</a> dan mereka langsung masuk daftar lagi. Kalau anaknya tetap datang hari ini, lunasi dulu lalu muat ulang halaman ini agar kehadirannya bisa dicatat.</div>
                </div>
            @endif

            @if($students->isEmpty())
                <p class="text-muted mb-0">
                    @if($suspendedStudents->isNotEmpty())
                        Semua murid di kelas ini sedang ditangguhkan, jadi belum ada yang bisa diabsen.
                    @else
                        Belum ada murid aktif di kelas ini.
                    @endif
                </p>
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
                            @foreach($students->values() as $i => $student)
                                <tr>
                                    <td class="fw-bold">{{ $student->name }} <span class="text-muted small">({{ $student->student_id }})</span>
                                        {{-- Penanda tagihan saja — kehadirannya tetap dicatat seperti biasa. --}}
                                        @if($student->hasArrears())
                                            <span class="badge bg-warning text-dark ms-1 fw-normal"
                                                  title="Murid {{ $student->paymentBlockReason() }}. Kehadiran tetap bisa dicatat; ingatkan orang tuanya.">
                                                <i class="bi bi-cash-coin me-1"></i>Menunggak {{ $student->arrearsDays() }} hari
                                            </span>
                                        @endif
                                        <input type="hidden" name="records[{{ $i }}][student_id]" value="{{ $student->id }}">
                                    </td>
                                    <td>
                                        {{-- data-no-search: hanya 3 opsi, kotak pencarian tidak perlu
                                             dan select native tidak terpotong overflow tabel. --}}
                                        <select name="records[{{ $i }}][status]" class="form-select form-select-sm" data-no-search>
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
