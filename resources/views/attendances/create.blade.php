@extends('layouts.app')

@section('content')
@php
    $tanggal = \Illuminate\Support\Carbon::parse($date);
    $tanggalPanjang = $tanggal->locale('id')->translatedFormat('l, j F Y');
    $jumlahPengganti = $rows->filter(fn ($row) => $row['replacement'])->count();
    $jumlahTercatat = $rows->filter(fn ($row) => $existing->has($row['student']->id))->count();
@endphp

@push('styles')
<style>
/* Segmented Radio Group & Buttons */
.btn-outline-permit {
    color: rgba(245, 136, 12, 1) !important;
    border-color: rgba(245, 136, 12, 1) !important;
    background-color: transparent;
}
.btn-outline-permit:hover,
.btn-outline-permit:focus,
.btn-outline-permit:active {
    color: #FFFFFF !important;
    background-color: rgba(245, 136, 12, 1) !important;
    border-color: rgba(245, 136, 12, 1) !important;
}
.btn-check:checked + .btn-outline-permit,
.btn-check:active + .btn-outline-permit,
.btn-check:focus + .btn-outline-permit {
    color: #FFFFFF !important;
    background-color: rgba(245, 136, 12, 1) !important;
    border-color: rgba(245, 136, 12, 1) !important;
    font-weight: 600;
}

.btn-outline-success {
    color: #15803D !important;
    border-color: #15803D !important;
}
.btn-outline-success:hover,
.btn-outline-success:focus,
.btn-outline-success:active {
    color: #FFFFFF !important;
    background-color: #15803D !important;
    border-color: #15803D !important;
}
.btn-check:checked + .btn-outline-success,
.btn-check:active + .btn-outline-success,
.btn-check:focus + .btn-outline-success {
    color: #FFFFFF !important;
    background-color: #15803D !important;
    border-color: #15803D !important;
    font-weight: 600;
}

.btn-outline-danger {
    color: #DC2626 !important;
    border-color: #DC2626 !important;
}
.btn-outline-danger:hover,
.btn-outline-danger:focus,
.btn-outline-danger:active {
    color: #FFFFFF !important;
    background-color: #DC2626 !important;
    border-color: #DC2626 !important;
}
.btn-check:checked + .btn-outline-danger,
.btn-check:active + .btn-outline-danger,
.btn-check:focus + .btn-outline-danger {
    color: #FFFFFF !important;
    background-color: #DC2626 !important;
    border-color: #DC2626 !important;
    font-weight: 600;
}
</style>
@endpush

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
                        <option value="{{ $class->id }}" @selected($selectedClass && $selectedClass->id === $class->id)>{{ $class->class_category }} ({{ $class->class_code }})</option>
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
                <span class="fw-semibold">{{ $selectedClass->class_category }} — {{ $tanggalPanjang }}</span>
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
                 paling umum — biasanya salah hari. Diberi peringatan, bukan
                 diblokir: murid pengganti tetap sah hadir. --}}
            @if(! $occursOnDate)
                <div class="alert alert-warning">
                    <div class="fw-bold mb-1"><i class="bi bi-calendar-x me-1"></i>{{ $selectedClass->class_category }} tidak ada sesi pada tanggal ini</div>
                    <p class="small mb-0">Jadwal rutinnya <strong>{{ $selectedClass->scheduleLabel() }}</strong>. Tanggal yang dipilih jatuh di hari lain — periksa lagi sebelum menyimpan.</p>
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
                                {{ $moved['replacement']->classRoom->class_category ?? 'kelas lain' }},
                                {{ $moved['replacement']->replacement_date->locale('id')->translatedFormat('l, j F Y') }}
                                pukul {{ \Illuminate\Support\Str::of($moved['replacement']->replacement_time)->substr(0, 5) }}@if($moved['lainnya'] > 0) (+{{ $moved['lainnya'] }} sesi pengganti lain)@endif.
                                <a href="{{ route('schedules.edit', $moved['replacement']) }}" class="alert-link ms-1"
                                   title="Ubah jadwal pengganti {{ $moved['student']->name }} di Manajemen Jadwal"><i class="bi bi-pencil"></i> ubah</a>
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
            <form action="{{ route('attendances.store') }}" method="POST" data-attendance-form>
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
                    <button type="button" class="btn btn-sm btn-outline-permit" data-bulk="permit"><i class="bi bi-envelope me-1"></i>Izin</button>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="text-muted small text-uppercase">
                            <tr>
                                <th>Murid</th>
                                <th style="width:110px;" class="text-center">Sesi Bulan Ini</th>
                                <th style="width:260px;">Status</th>
                                <th>Catatan</th>
                                <th style="width:170px;">Replacement?</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $i => $row)
                                @php
                                    $student = $row['student'];
                                    $replacement = $row['replacement'];
                                    $tercatat = $existing->get($student->id);
                                    $status = old("records.$i.status", $tercatat->status ?? 'present');

                                    // Sesi bulan ini yang sudah dihadiri, dari jatah bulanan.
                                    $sesiHadir = (int) ($sessionCounts[$student->id] ?? 0);
                                    $sesiPenuh = $sesiHadir >= $sessionQuota;

                                    // Request pengganti yang sudah ada untuk murid ini: entah ia
                                    // datang ke sini sebagai pengganti, atau justru sudah mengajukan
                                    // pindah dari sesi ini dan menunggu persetujuan. Keduanya sudah
                                    // punya barisnya sendiri di Manajemen Jadwal, jadi pensilnya
                                    // mengarah ke sana — bukan membuat pengajuan baru.
                                    // Dropdown terbuka di "Ya" untuk murid yang memang punya jadwal
                                    // pengganti — apa pun bentuknya: datang ke sesi ini sebagai
                                    // pengganti, mengajukan pindah dari sesi ini, atau punya sesi
                                    // pengganti lain yang belum lewat.
                                    $requestPengganti = $replacement ?: ($row['pending'] ?: $row['others']->first());
                                    $tautanJadwal = $requestPengganti
                                        ? route('schedules.edit', $requestPengganti)
                                        : route('schedules.create', [
                                            'student_id' => $student->id,
                                            'origin_class_id' => $selectedClass->id,
                                            'missed_date' => $date,
                                        ]);
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
                                                <strong>{{ $replacement->originClass->class_category ?? 'kelas lain' }}</strong>,
                                                pukul {{ \Illuminate\Support\Str::of($replacement->replacement_time)->substr(0, 5) }}
                                                {{-- Sesi yang digantikan: tanpa ini "pengganti" tidak
                                                     menjelaskan sesi mana yang sedang disusulnya. --}}
                                                @if($replacement->missed_date)
                                                    <span class="text-muted">· menggantikan sesi {{ $replacement->missed_date->locale('id')->translatedFormat('j M Y') }}</span>
                                                @endif
                                                @if($replacement->reason)<span class="text-muted">· {{ $replacement->reason }}</span>@endif
                                            </div>
                                        @endif

                                        {{-- Penanda tagihan saja — kehadirannya tetap dicatat seperti biasa. --}}
                                        @if($student->hasArrears())
                                            <div class="mt-1">
                                                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: rgba(245, 136, 12, 1);"
                                                      title="Murid {{ $student->paymentBlockReason() }}. Kehadiran tetap bisa dicatat; ingatkan orang tuanya.">
                                                    <i class="bi bi-cash-coin me-1"></i>Menunggak {{ $student->arrearsDays() }} hari
                                                </span>
                                            </div>
                                        @endif

                                        <input type="hidden" name="records[{{ $i }}][student_id]" value="{{ $student->id }}">
                                    </td>
                                    {{-- Jatah sesi bulan ini. Dihitung dari kehadiran yang sudah
                                         tersimpan, jadi sesi yang sedang diisi baru ikut terhitung
                                         setelah absensinya disimpan. --}}
                                    <td class="text-center">
                                        <span class="badge rounded-pill {{ $sesiPenuh ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}"
                                              title="{{ $student->name }} sudah hadir {{ $sesiHadir }} dari {{ $sessionQuota }} sesi pada {{ $tanggal->locale('id')->translatedFormat('F Y') }} (termasuk sesi pengganti di kelas lain).">
                                            {{ $sesiHadir }}/{{ $sessionQuota }}
                                        </span>
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
                                            <label class="btn btn-outline-permit" for="izin-{{ $i }}">Izin</label>
                                        </div>
                                    </td>
                                    <td><input type="text" name="records[{{ $i }}][notes]" class="form-control form-control-sm" placeholder="opsional" value="{{ old("records.$i.notes", $tercatat->notes ?? '') }}"></td>
                                    {{-- Replacement? — pintasan ke Manajemen Jadwal, bukan data
                                         absensi: jadwal pengganti hidup di ReplacementRequest, dan
                                         menyimpannya lagi di sini hanya akan jadi dua sumber
                                         kebenaran. Karena itu "Tidak" memang tidak berbuat apa-apa,
                                         dan "Ya" menahan penyimpanan sampai tanggal penggantinya
                                         benar-benar diatur (lihat assertReplacementsScheduled).
                                         Nilainya diisi dari request yang sudah ada, supaya pilihan
                                         "Ya" tidak hilang begitu halaman dimuat ulang. --}}
                                    @php $pilihanPengganti = old("records.$i.replacement", $requestPengganti ? 'ya' : 'tidak'); @endphp
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <select name="records[{{ $i }}][replacement]" class="form-select form-select-sm"
                                                    data-replacement-toggle
                                                    data-has-request="{{ $requestPengganti ? 1 : 0 }}"
                                                    data-student="{{ $student->name }}"
                                                    data-schedule-url="{{ $tautanJadwal }}"
                                                    aria-label="Replacement untuk {{ $student->name }}">
                                                <option value="tidak" @selected($pilihanPengganti !== 'ya')>Tidak</option>
                                                <option value="ya" @selected($pilihanPengganti === 'ya')>Ya</option>
                                            </select>
                                            <a href="{{ $tautanJadwal }}"
                                               class="btn btn-sm btn-outline-primary flex-shrink-0 {{ $pilihanPengganti === 'ya' ? '' : 'd-none' }}"
                                               data-replacement-edit
                                               title="{{ $requestPengganti ? 'Ubah tanggal & kelas pengganti '.$student->name.' di Manajemen Jadwal' : 'Atur jadwal pengganti '.$student->name.' di Manajemen Jadwal' }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                        {{-- Satu badge saja, bukan daftar jadwal: merinci setiap sesi
                                             pengganti membuat kolom ini tak terbaca, sementara yang
                                             perlu diketahui admin cuma "anak ini replacement" dan sesi
                                             terdekatnya. Rinciannya ada di tooltip & Manajemen Jadwal. --}}
                                        @php
                                            $jamPengganti = fn ($req) => \Illuminate\Support\Str::of($req->replacement_time)->substr(0, 5);
                                            $sesiPengganti = fn ($req) => $req->replacement_date->locale('id')->translatedFormat('j M Y').' · '.$jamPengganti($req)
                                                .' ('.($req->classRoom->class_category ?? 'kelas lain').')';

                                            $badge = match (true) {
                                                (bool) $replacement => [
                                                    'warna' => 'bg-info-subtle text-info-emphasis',
                                                    'teks' => 'Sesi pengganti · '.$jamPengganti($replacement),
                                                    'judul' => 'Hadir di sesi ini sebagai pengganti sesi '
                                                        .($replacement->missed_date?->locale('id')->translatedFormat('j M Y') ?? 'sebelumnya')
                                                        .' dari '.($replacement->originClass->class_category ?? 'kelas lain').'.',
                                                ],
                                                (bool) $row['pending'] => [
                                                    'warna' => 'bg-warning-subtle text-warning-emphasis',
                                                    'teks' => $row['pending']->request_status === 'rejected'
                                                        ? 'Pengajuan ditolak'
                                                        : 'Menunggu persetujuan · '.$row['pending']->replacement_date->locale('id')->translatedFormat('j M Y'),
                                                    'judul' => 'Diminta pindah ke '.$sesiPengganti($row['pending']).'. Sesinya belum berpindah sampai disetujui.',
                                                ],
                                                $row['others']->isNotEmpty() => [
                                                    'warna' => 'bg-secondary-subtle text-secondary-emphasis',
                                                    'teks' => 'Replacement · '.$row['others']->first()->replacement_date->locale('id')->translatedFormat('j M Y')
                                                        .($row['others']->count() > 1 ? ' +'.($row['others']->count() - 1) : ''),
                                                    'judul' => 'Jadwal pengganti: '.$row['others']->map($sesiPengganti)->implode('; ').'.',
                                                ],
                                                default => null,
                                            };
                                        @endphp
                                        @if($badge)
                                            <div class="mt-1">
                                                <span class="badge rounded-pill {{ $badge['warna'] }}" title="{{ $badge['judul'] }}">
                                                    <i class="bi bi-arrow-left-right me-1"></i>{{ $badge['teks'] }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Penjaga di layar untuk aturan yang juga ditegakkan server:
                     absensi tidak bisa disimpan selama masih ada "Replacement: Ya"
                     yang tanggal penggantinya belum ada. Muncul begitu Ya dipilih,
                     bukan menunggu tombol simpan ditekan. --}}
                <div class="alert alert-warning d-none" id="replacementGuard" role="alert">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Jadwal pengganti belum diatur</div>
                    <p class="small mb-2">
                        <span id="replacementGuardNames"></span> ditandai <strong>Replacement: Ya</strong>, tapi tanggal penggantinya belum ada.
                        Atur dulu jadwalnya lewat tombol pensil di kolom Replacement — absensinya baru bisa disimpan setelah itu.
                        <span class="d-block mt-1 text-muted">Pilihan hadir/absen yang belum disimpan akan hilang saat berpindah halaman.</span>
                    </p>
                    <a href="#" class="btn btn-sm btn-warning" id="replacementGuardLink"><i class="bi bi-pencil me-1"></i>Atur jadwal pengganti sekarang</a>
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

    // Kolom "Replacement?": memilih Ya memunculkan tombol pensilnya. Pilihannya
    // sendiri tidak disimpan sebagai data absensi — yang menyimpan keadaan adalah
    // request pengganti yang dibuat lewat tombol itu, dan itulah yang dibaca ulang
    // saat halaman ini dibuka lagi.
    var toggles = document.querySelectorAll('[data-replacement-toggle]');
    var guard = document.getElementById('replacementGuard');
    var guardNames = document.getElementById('replacementGuardNames');
    var guardLink = document.getElementById('replacementGuardLink');

    // "Ya" yang jadwal penggantinya belum ada — inilah yang menahan penyimpanan.
    function belumTerjadwal() {
        return Array.prototype.filter.call(toggles, function (select) {
            return select.value === 'ya' && select.dataset.hasRequest === '0';
        });
    }

    // Peringatannya baru muncul setelah tombol simpan ditekan: memilih "Ya" saja
    // belum keliru — admin memang sedang menuju tombol pensilnya. Setelah tampil,
    // isinya terus mengikuti pilihan di tabel dan hilang sendiri begitu semuanya
    // beres.
    var penjagaTampil = false;

    function perbaruiPenjaga() {
        var tertunda = belumTerjadwal();

        if (! guard || ! penjagaTampil) {
            return tertunda;
        }

        guard.classList.toggle('d-none', tertunda.length === 0);

        if (tertunda.length) {
            guardNames.textContent = tertunda.map(function (select) {
                return select.dataset.student;
            }).join(', ');
            // Tautan ke murid pertama; sisanya diurus lewat pensil di barisnya.
            guardLink.href = tertunda[0].dataset.scheduleUrl;
        }

        return tertunda;
    }

    toggles.forEach(function (select) {
        select.addEventListener('change', function () {
            var link = select.parentElement.querySelector('[data-replacement-edit]');
            if (link) {
                link.classList.toggle('d-none', select.value !== 'ya');
            }
            perbaruiPenjaga();
        });
    });

    // Server memeriksa hal yang sama saat menyimpan; ini hanya supaya admin tidak
    // perlu menunggu satu putaran request untuk tahu.
    var form = document.querySelector('[data-attendance-form]');
    if (form) {
        form.addEventListener('submit', function (event) {
            if (belumTerjadwal().length === 0) {
                return;
            }

            event.preventDefault();
            penjagaTampil = true;
            perbaruiPenjaga();

            if (guard) {
                guard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
});
</script>
@endpush
