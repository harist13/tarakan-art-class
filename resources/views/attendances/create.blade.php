@extends('layouts.app')

@section('content')
@php
    $tanggal = \Illuminate\Support\Carbon::parse($date);
    $tanggalPanjang = $tanggal->locale('id')->translatedFormat('l, j F Y');
    $jumlahPengganti = $rows->filter(fn ($row) => $row['replacement'])->count();
    $jumlahTercatat = $rows->filter(fn ($row) => $existing->has($row['student']->id))->count();
@endphp

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800 fw-bold">Input Absensi</h1>
        <p class="text-muted small mb-0">Absensi dicatat per sesi — satu kelas pada satu tanggal.</p>
    </div>
    <a href="{{ route('attendances.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

{{-- ── 1. Sesi yang diabsen ──────────────────────────────────────────────
     Kelas & tanggal dipilih bersama dalam satu form GET. Tanggalnya ikut
     menentukan isi daftar — bukan sekadar label yang disimpan — karena
     replacement class memindahkan murid antar sesi. --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="badge rounded-pill text-bg-primary">1</span>
        <span class="fw-semibold">Pilih sesi yang diabsen</span>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('attendances.create') }}" class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="class_id">Kelas</label>
                <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()" required>
                    <option value="">— Pilih Kelas —</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass && $selectedClass->id === $class->id)>{{ $class->class_name }} ({{ $class->class_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="date">Tanggal sesi</label>
                <input type="date" name="date" id="date" class="form-control" value="{{ $date }}" onchange="this.form.submit()">
            </div>
            <div class="col-12">
                <div class="form-text mb-0"><i class="bi bi-info-circle me-1"></i>Daftar murid menyesuaikan kelas <strong>dan</strong> tanggalnya, termasuk murid yang sesinya pindah.</div>
            </div>
        </form>
    </div>
</div>

@if($selectedClass)
    {{-- ── 2. Daftar hadir ───────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill text-bg-primary">2</span>
                <span class="fw-semibold">{{ $selectedClass->class_name }} — {{ $tanggalPanjang }}</span>
            </span>
            <span class="d-flex flex-wrap gap-2">
                <span class="badge bg-primary-subtle text-primary-emphasis">{{ $rows->count() }} murid diabsen</span>
                @if($jumlahPengganti)
                    <span class="badge bg-info-subtle text-info-emphasis">{{ $jumlahPengganti }} murid pengganti</span>
                @endif
                @if($jumlahTercatat)
                    <span class="badge bg-success-subtle text-success-emphasis">{{ $jumlahTercatat }} sudah tercatat</span>
                @endif
            </span>
        </div>
        <div class="card-body">
            {{-- Tanggal yang tidak jatuh pada sesi kelas ini adalah kekeliruan
                 paling umum: salah hari, atau tanggalnya hari libur. Diberi
                 peringatan, bukan diblokir — murid pengganti tetap sah hadir. --}}
            @if(! $occursOnDate)
                <div class="alert alert-warning">
                    <div class="fw-bold mb-1"><i class="bi bi-calendar-x me-1"></i>{{ $selectedClass->class_name }} tidak ada sesi pada tanggal ini</div>
                    <p class="small mb-0">Jadwal rutinnya <strong>{{ $selectedClass->scheduleLabel() }}</strong>. Tanggal yang dipilih jatuh di hari lain atau pada hari libur — periksa lagi sebelum menyimpan.</p>
                </div>
            @endif

            {{-- Murid yang sesinya dipindahkan ke kelas/tanggal lain. Sengaja tidak
                 dimasukkan ke daftar absen: mencatatnya "absen" akan merusak rekap
                 murid yang justru sudah mengurus penggantinya. --}}
            @if($movedOut->isNotEmpty())
                <div class="alert alert-info">
                    <div class="fw-bold mb-1"><i class="bi bi-box-arrow-right me-1"></i>{{ $movedOut->count() }} murid memindahkan sesi ini</div>
                    <ul class="small mb-0 ps-3">
                        @foreach($movedOut as $moved)
                            <li>
                                <strong>{{ $moved['student']->name }}</strong> ({{ $moved['student']->student_id }}) — pindah ke
                                {{ $moved['replacement']->classRoom->class_name ?? 'kelas lain' }},
                                {{ $moved['replacement']->replacement_date->locale('id')->translatedFormat('l, j F Y') }}
                                pukul {{ \Illuminate\Support\Str::of($moved['replacement']->replacement_time)->substr(0, 5) }}.
                            </li>
                        @endforeach
                    </ul>
                    <div class="small mt-2 mb-0">Mereka tidak perlu diabsen di sesi ini — kehadirannya dicatat di sesi penggantinya.</div>
                </div>
            @endif

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

            @if($rows->isEmpty())
                <p class="text-muted mb-0">
                    @if($suspendedStudents->isNotEmpty())
                        Semua murid di kelas ini sedang ditangguhkan, jadi belum ada yang bisa diabsen.
                    @elseif($movedOut->isNotEmpty())
                        Semua murid kelas ini memindahkan sesinya, jadi tidak ada yang perlu diabsen pada tanggal ini.
                    @else
                        Belum ada murid aktif di kelas ini.
                    @endif
                </p>
            @else
            <form action="{{ route('attendances.store') }}" method="POST">
                @csrf
                <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">
                {{-- Tanggalnya sudah ditetapkan di langkah 1; kalau bisa diubah lagi
                     di sini, daftar muridnya tidak lagi cocok dengan tanggalnya. --}}
                <input type="hidden" name="attendance_date" value="{{ $date }}">

                @if($jumlahTercatat)
                    <div class="alert alert-light border small d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-clock-history"></i>
                        <span>Sesi ini sudah pernah diabsen — pilihan di bawah menampilkan catatan yang tersimpan. Menyimpan lagi akan memperbaruinya.</span>
                    </div>
                @endif

                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="small text-muted">Tandai semua:</span>
                    <button type="button" class="btn btn-sm btn-outline-success" data-bulk="present"><i class="bi bi-check-lg me-1"></i>Hadir</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bulk="absent"><i class="bi bi-x-lg me-1"></i>Absen</button>
                    <button type="button" class="btn btn-sm btn-outline-warning" data-bulk="permit"><i class="bi bi-envelope me-1"></i>Izin</button>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="text-muted small text-uppercase">
                            <tr><th>Murid</th><th style="width:260px;">Status</th><th>Catatan</th></tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $i => $row)
                                @php
                                    $student = $row['student'];
                                    $replacement = $row['replacement'];
                                    $tercatat = $existing->get($student->id);
                                    $status = old("records.$i.status", $tercatat->status ?? 'present');
                                @endphp
                                <tr @class(['table-info bg-opacity-10' => (bool) $replacement])>
                                    <td>
                                        <span class="fw-bold">{{ $student->name }}</span>
                                        <span class="text-muted small">({{ $student->student_id }})</span>
                                        @if($tercatat)
                                            <i class="bi bi-check-circle-fill text-success ms-1" title="Sudah tercatat sebelumnya"></i>
                                        @endif

                                        {{-- Murid pengganti: tanpa keterangan ini, admin melihat nama
                                             asing di kelasnya dan tak tahu kenapa ia ada di sini. --}}
                                        @if($replacement)
                                            <div class="small text-info-emphasis mt-1">
                                                <i class="bi bi-box-arrow-in-right me-1"></i>Murid pengganti dari
                                                <strong>{{ $replacement->originClass->class_name ?? 'kelas lain' }}</strong>,
                                                pukul {{ \Illuminate\Support\Str::of($replacement->replacement_time)->substr(0, 5) }}
                                                @if($replacement->reason)<span class="text-muted">· {{ $replacement->reason }}</span>@endif
                                            </div>
                                        @endif

                                        {{-- Penanda tagihan saja — kehadirannya tetap dicatat seperti biasa. --}}
                                        @if($student->hasArrears())
                                            <div class="mt-1">
                                                <span class="badge bg-warning text-dark fw-normal"
                                                      title="Murid {{ $student->paymentBlockReason() }}. Kehadiran tetap bisa dicatat; ingatkan orang tuanya.">
                                                    <i class="bi bi-cash-coin me-1"></i>Menunggak {{ $student->arrearsDays() }} hari
                                                </span>
                                            </div>
                                        @endif

                                        <input type="hidden" name="records[{{ $i }}][student_id]" value="{{ $student->id }}">
                                    </td>
                                    <td>
                                        {{-- Tombol pilihan, bukan dropdown: mengabsen satu kelas berarti
                                             puluhan kali memilih, dan tombol cukup sekali klik. --}}
                                        <div class="btn-group btn-group-sm w-100" role="group" aria-label="Status kehadiran {{ $student->name }}">
                                            <input type="radio" class="btn-check" name="records[{{ $i }}][status]" id="hadir-{{ $i }}" value="present" @checked($status === 'present')>
                                            <label class="btn btn-outline-success" for="hadir-{{ $i }}">Hadir</label>
                                            <input type="radio" class="btn-check" name="records[{{ $i }}][status]" id="absen-{{ $i }}" value="absent" @checked($status === 'absent')>
                                            <label class="btn btn-outline-danger" for="absen-{{ $i }}">Absen</label>
                                            <input type="radio" class="btn-check" name="records[{{ $i }}][status]" id="izin-{{ $i }}" value="permit" @checked($status === 'permit')>
                                            <label class="btn btn-outline-warning" for="izin-{{ $i }}">Izin</label>
                                        </div>
                                    </td>
                                    <td><input type="text" name="records[{{ $i }}][notes]" class="form-control form-control-sm" placeholder="opsional" value="{{ old("records.$i.notes", $tercatat->notes ?? '') }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Absensi</button>
            </form>
            @endif
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Tandai semua sekaligus — mayoritas murid biasanya hadir, jadi menyetel
    // seluruh baris lalu membetulkan yang menyimpang jauh lebih cepat.
    document.querySelectorAll('[data-bulk]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.btn-check[value="' + btn.dataset.bulk + '"]').forEach(function (input) {
                input.checked = true;
            });
        });
    });
});
</script>
@endpush
