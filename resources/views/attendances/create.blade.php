@extends('layouts.app')

@section('content')
@php
    $hari = \Illuminate\Support\Carbon::parse($date);
    $hariPanjang = $hari->locale('id')->translatedFormat('l, j F Y');
@endphp

@push('styles')
<style>
/* Kotak centang kehadiran — sengaja besar: mengabsen satu kelas berarti belasan
   kali mengklik, dan kotak ukuran bawaan terlalu mudah meleset. */
.absen-check { width: 1.35rem; height: 1.35rem; }

/* Tombol izin: netral saat mati, amber saat menyala. */
.btn-check:checked + .btn-izin {
    color: #FFFFFF !important;
    background-color: rgba(245, 136, 12, 1) !important;
    border-color: rgba(245, 136, 12, 1) !important;
}
</style>
@endpush

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800 fw-bold">Absensi</h1>
        <p class="text-muted small mb-0">Daftarnya ditarik dari jadwal — tinggal centang siapa yang hadir.</p>
    </div>
    <a href="{{ route('attendances.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i>Rekap Kehadiran</a>
</div>

{{-- ── Hari yang diabsen ─────────────────────────────────────────────────
     Satu-satunya pilihan di halaman ini. Kelas tidak dipilih manual: sesi yang
     berjalan hari itu sudah ditentukan jadwalnya sendiri. --}}
<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('attendances.create', ['date' => $hari->copy()->subDay()->toDateString()]) }}"
           class="btn btn-sm btn-outline-secondary" title="Hari sebelumnya"><i class="bi bi-chevron-left"></i></a>

        <form method="GET" action="{{ route('attendances.create') }}">
            <input type="date" name="date" class="form-control form-control-sm" style="width:auto;"
                   value="{{ $date }}" onchange="this.form.submit()" aria-label="Tanggal absensi">
        </form>

        <a href="{{ route('attendances.create', ['date' => $hari->copy()->addDay()->toDateString()]) }}"
           class="btn btn-sm btn-outline-secondary" title="Hari berikutnya"><i class="bi bi-chevron-right"></i></a>

        <span class="fw-semibold ms-2">{{ $hariPanjang }}</span>

        @if(! $hari->isToday())
            <a href="{{ route('attendances.create') }}" class="btn btn-sm btn-link ms-auto">Kembali ke hari ini</a>
        @endif
    </div>
</div>

@forelse($sessions as $sesi)
    @php
        $kelas = $sesi['class'];
        $sudahTercatat = $sesi['rows']->filter(fn ($row) => $sesi['existing']->has($row['student']->id))->count();
        $sudahHadir = $sesi['rows']->filter(fn ($row) => optional($sesi['existing']->get($row['student']->id))->status === 'present')->count();
    @endphp
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="d-flex align-items-center gap-2 flex-wrap">
                <i class="bi bi-clock text-muted"></i>
                <span class="fw-semibold">{{ $kelas->timeRangeLabel() }}</span>
                <span class="fw-bold">{{ $kelas->class_category }}</span>
                <span class="text-muted small">{{ $kelas->class_code }} · {{ $kelas->tutor->name ?? 'tanpa tutor' }}</span>
            </span>
            @if($sudahTercatat)
                <span class="badge bg-success-subtle text-success-emphasis">{{ $sudahHadir }} dari {{ $sesi['rows']->count() }} hadir</span>
            @else
                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $sesi['rows']->count() }} murid · belum diabsen</span>
            @endif
        </div>
        <div class="card-body">
            @if($sesi['rows']->isEmpty())
                <p class="text-muted small mb-0">Tidak ada murid yang perlu diabsen di sesi ini.</p>
            @else
                <form action="{{ route('attendances.store') }}" method="POST" data-absensi>
                    @csrf
                    <input type="hidden" name="class_id" value="{{ $kelas->id }}">
                    <input type="hidden" name="attendance_date" value="{{ $date }}">

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="text-muted small text-uppercase">
                                <tr>
                                    <th style="width:52px;" class="text-center">Hadir</th>
                                    <th>Murid</th>
                                    <th style="width:100px;" class="text-center">Sesi Bulan Ini</th>
                                    <th style="width:170px;">Replacement?</th>
                                    <th style="width:96px;" class="text-end">Izin · Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sesi['rows'] as $row)
                                    @php
                                        $murid = $row['student'];
                                        $replacement = $row['replacement'];
                                        $tercatat = $sesi['existing']->get($murid->id);
                                        $hadir = $tercatat?->status === 'present';
                                        $izin = $tercatat?->status === 'permit';

                                        $sesiHadir = (int) ($sessionCounts[$murid->id] ?? 0);
                                        $sesiPenuh = $sesiHadir >= $sessionQuota;

                                        // Dropdown terbuka di "Ya" untuk murid yang memang punya
                                        // jadwal pengganti — apa pun bentuknya: datang ke sesi ini
                                        // sebagai pengganti, mengajukan pindah dari sesi ini, atau
                                        // punya sesi pengganti lain yang belum lewat.
                                        $requestPengganti = $replacement ?: ($row['pending'] ?: $row['others']->first());
                                        $tautanJadwal = $requestPengganti
                                            ? route('schedules.edit', $requestPengganti)
                                            : route('schedules.create', [
                                                'student_id' => $murid->id,
                                                'origin_class_id' => $kelas->id,
                                                'missed_date' => $date,
                                            ]);
                                        $pilihanPengganti = $requestPengganti ? 'ya' : 'tidak';
                                    @endphp
                                    <tr @class(['table-info bg-opacity-10' => (bool) $replacement])>
                                        <td class="text-center">
                                            <input type="hidden" name="students[]" value="{{ $murid->id }}">
                                            <input type="checkbox" class="form-check-input absen-check" data-hadir
                                                   name="present[]" value="{{ $murid->id }}" @checked($hadir)
                                                   aria-label="Kehadiran {{ $murid->name }}">
                                        </td>
                                        <td>
                                            <span class="fw-semibold">{{ $murid->name }}</span>
                                            <span class="text-muted small">({{ $murid->student_id }})</span>

                                            {{-- Murid pengganti: tanpa keterangan ini, admin melihat
                                                 nama asing di kelasnya dan tak tahu kenapa ia di sini. --}}
                                            @if($replacement)
                                                <span class="badge rounded-pill bg-info-subtle text-info-emphasis ms-1"
                                                      title="Menggantikan sesi{{ $replacement->missed_date ? ' '.$replacement->missed_date->locale('id')->translatedFormat('j M Y') : '' }} dari {{ $replacement->originClass->class_category ?? 'kelas lain' }}.">
                                                    <i class="bi bi-box-arrow-in-right me-1"></i>pengganti dari {{ $replacement->originClass->class_category ?? 'kelas lain' }}
                                                </span>
                                            @endif

                                            {{-- Penanda tagihan saja — kehadirannya tetap dicatat. --}}
                                            @if($murid->hasArrears())
                                                <span class="badge rounded-pill px-2 text-white ms-1" style="background-color: rgba(245, 136, 12, 1);"
                                                      title="Murid {{ $murid->paymentBlockReason() }}. Kehadiran tetap bisa dicatat; ingatkan orang tuanya.">
                                                    <i class="bi bi-cash-coin me-1"></i>menunggak {{ $murid->arrearsDays() }} hari
                                                </span>
                                            @endif

                                            {{-- Catatan disembunyikan sampai dibutuhkan: sebagian besar
                                                 baris tidak pernah memerlukannya, dan kolom teks di tiap
                                                 baris membuat daftar centang ini ramai kembali. --}}
                                            <div class="mt-2 {{ filled($tercatat?->notes) ? '' : 'd-none' }}" data-catatan>
                                                <input type="text" name="notes[{{ $murid->id }}]" class="form-control form-control-sm"
                                                       placeholder="catatan untuk {{ $murid->name }}" value="{{ $tercatat->notes ?? '' }}">
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill {{ $sesiPenuh ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}"
                                                  title="{{ $murid->name }} sudah hadir {{ $sesiHadir }} dari {{ $sessionQuota }} sesi pada {{ $hari->locale('id')->translatedFormat('F Y') }} (termasuk sesi pengganti di kelas lain).">
                                                {{ $sesiHadir }}/{{ $sessionQuota }}
                                            </span>
                                        </td>
                                        {{-- Replacement? — pintasan ke Manajemen Jadwal, bukan data
                                             absensi: jadwal pengganti hidup di ReplacementRequest.
                                             "Tidak" memang tidak berbuat apa-apa; "Ya" menahan
                                             penyimpanan sampai tanggalnya benar-benar diatur. --}}
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <select name="replacement[{{ $murid->id }}]" class="form-select form-select-sm"
                                                        data-replacement-toggle
                                                        data-has-request="{{ $requestPengganti ? 1 : 0 }}"
                                                        data-student="{{ $murid->name }}"
                                                        data-schedule-url="{{ $tautanJadwal }}"
                                                        aria-label="Replacement untuk {{ $murid->name }}">
                                                    <option value="tidak" @selected($pilihanPengganti !== 'ya')>Tidak</option>
                                                    <option value="ya" @selected($pilihanPengganti === 'ya')>Ya</option>
                                                </select>
                                                <a href="{{ $tautanJadwal }}"
                                                   class="btn btn-sm btn-outline-primary flex-shrink-0 {{ $pilihanPengganti === 'ya' ? '' : 'd-none' }}"
                                                   data-replacement-edit
                                                   title="{{ $requestPengganti ? 'Ubah tanggal & kelas pengganti '.$murid->name : 'Atur jadwal pengganti '.$murid->name }} di Manajemen Jadwal">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                            @php
                                                // Satu badge saja, bukan daftar jadwal: yang perlu
                                                // diketahui admin cuma "anak ini replacement" dan sesi
                                                // terdekatnya. Rinciannya di tooltip & Manajemen Jadwal.
                                                $jamPengganti = fn ($req) => \Illuminate\Support\Str::of($req->replacement_time)->substr(0, 5);
                                                $sesiPengganti = fn ($req) => $req->replacement_date->locale('id')->translatedFormat('j M Y').' · '.$jamPengganti($req)
                                                    .' ('.($req->classRoom->class_category ?? 'kelas lain').')';

                                                $badge = match (true) {
                                                    (bool) $replacement => null, // sudah ditandai di kolom murid
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
                                        <td class="text-end text-nowrap">
                                            {{-- Izin: jalan keluar untuk yang berhalangan, bukan pilihan
                                                 sehari-hari. Menyala berarti status izin, dan centang
                                                 hadirnya ikut dilepas — keduanya tak mungkin benar. --}}
                                            <input type="checkbox" class="btn-check" id="izin-{{ $kelas->id }}-{{ $murid->id }}"
                                                   name="permit[]" value="{{ $murid->id }}" data-izin @checked($izin)>
                                            <label class="btn btn-sm btn-outline-secondary btn-izin" for="izin-{{ $kelas->id }}-{{ $murid->id }}"
                                                   title="Tandai {{ $murid->name }} izin"><i class="bi bi-envelope"></i></label>

                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-catatan-toggle
                                                    title="Tulis catatan untuk {{ $murid->name }}"><i class="bi bi-pencil-square"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Penjaga di layar untuk aturan yang juga ditegakkan server:
                         absensi tidak bisa disimpan selama masih ada "Replacement: Ya"
                         yang tanggal penggantinya belum ada. Muncul saat simpan ditekan. --}}
                    <div class="alert alert-warning d-none mt-3" data-guard role="alert">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Jadwal pengganti belum diatur</div>
                        <p class="small mb-2">
                            <span data-guard-names></span> ditandai <strong>Replacement: Ya</strong>, tapi tanggal penggantinya belum ada.
                            Atur dulu jadwalnya lewat tombol pensil di kolom Replacement — absensinya baru bisa disimpan setelah itu.
                            <span class="d-block mt-1 text-muted">Centang yang belum disimpan akan hilang saat berpindah halaman.</span>
                        </p>
                        <a href="#" class="btn btn-sm btn-warning" data-guard-link><i class="bi bi-pencil me-1"></i>Atur jadwal pengganti sekarang</a>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-centang-semua><i class="bi bi-check-all me-1"></i>Centang semua</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-hapus-centang>Kosongkan</button>
                        @if($sudahTercatat)
                            <span class="small text-muted"><i class="bi bi-clock-history me-1"></i>Sesi ini sudah diabsen — menyimpan lagi akan memperbaruinya.</span>
                        @else
                            <span class="small text-muted">Yang tidak dicentang tercatat tidak hadir.</span>
                        @endif
                    </div>
                </form>
            @endif

            {{-- Murid yang sesinya dipindahkan. Sengaja tidak bisa dicentang di sini:
                 kehadirannya dicatat di sesi penggantinya. --}}
            @if($sesi['movedOut']->isNotEmpty())
                <div class="small text-muted mt-3">
                    <i class="bi bi-box-arrow-right me-1"></i>Jadwalnya pindah, tidak diabsen di sesi ini:
                    <ul class="mb-0 ps-3">
                        @foreach($sesi['movedOut'] as $moved)
                            <li>
                                {{ $moved['student']->name }} — ke
                                {{ $moved['replacement']->classRoom->class_category ?? 'kelas lain' }},
                                {{ $moved['replacement']->replacement_date->locale('id')->translatedFormat('j M Y') }}
                                pukul {{ \Illuminate\Support\Str::of($moved['replacement']->replacement_time)->substr(0, 5) }}@if($moved['lainnya'] > 0) (+{{ $moved['lainnya'] }} sesi lain)@endif
                                <a href="{{ route('schedules.edit', $moved['replacement']) }}" title="Ubah jadwalnya di Manajemen Jadwal"><i class="bi bi-pencil"></i></a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Murid yang ditangguhkan sistem karena tunggakan lewat masa toleransi. --}}
            @if($sesi['suspended']->isNotEmpty())
                <div class="small text-warning-emphasis mt-3">
                    <i class="bi bi-pause-circle-fill me-1"></i>Ditangguhkan karena tunggakan:
                    {{ $sesi['suspended']->pluck('name')->implode(', ') }}.
                    Lunasi di <a href="{{ route('payments.index') }}">menu Pembayaran</a> agar kembali masuk daftar.
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
            <p class="mb-1">Tidak ada sesi kelas pada {{ $hariPanjang }}.</p>
            <p class="small mb-0">Jadwal kelas diatur di <a href="{{ route('classes.index') }}">Manajemen Kelas</a>, kelas pengganti di <a href="{{ route('schedules.index') }}">Jadwal &amp; Replacement</a>.</p>
        </div>
    </div>
@endforelse
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-absensi]').forEach(function (form) {
        var guard = form.querySelector('[data-guard]');
        var guardNames = form.querySelector('[data-guard-names]');
        var guardLink = form.querySelector('[data-guard-link]');
        var penjagaTampil = false;

        // ── Centang massal ───────────────────────────────────────────────
        // Mayoritas murid biasanya hadir, jadi menyetel satu kelas lalu
        // membetulkan yang menyimpang jauh lebih cepat. Yang izin dilewati:
        // menyetelnya jadi hadir justru menghapus keterangan yang sudah benar.
        function setSemua(nilai) {
            form.querySelectorAll('[data-hadir]').forEach(function (kotak) {
                var izin = kotak.closest('tr').querySelector('[data-izin]');
                if (nilai && izin && izin.checked) {
                    return;
                }
                kotak.checked = nilai;
            });
        }

        var centang = form.querySelector('[data-centang-semua]');
        var kosong = form.querySelector('[data-hapus-centang]');
        if (centang) {
            centang.addEventListener('click', function () { setSemua(true); });
        }
        if (kosong) {
            kosong.addEventListener('click', function () { setSemua(false); });
        }

        // ── Izin ⇄ hadir saling meniadakan ───────────────────────────────
        form.querySelectorAll('[data-izin]').forEach(function (izin) {
            izin.addEventListener('change', function () {
                var hadir = izin.closest('tr').querySelector('[data-hadir]');
                if (izin.checked && hadir) {
                    hadir.checked = false;
                }
            });
        });

        form.querySelectorAll('[data-hadir]').forEach(function (hadir) {
            hadir.addEventListener('change', function () {
                var izin = hadir.closest('tr').querySelector('[data-izin]');
                if (hadir.checked && izin) {
                    izin.checked = false;
                }
            });
        });

        // ── Catatan: tersembunyi sampai diminta ──────────────────────────
        form.querySelectorAll('[data-catatan-toggle]').forEach(function (tombol) {
            tombol.addEventListener('click', function () {
                var kotak = tombol.closest('tr').querySelector('[data-catatan]');
                if (! kotak) {
                    return;
                }
                kotak.classList.toggle('d-none');
                var isian = kotak.querySelector('input');
                if (isian && ! kotak.classList.contains('d-none')) {
                    isian.focus();
                }
            });
        });

        // ── Replacement ──────────────────────────────────────────────────
        // "Ya" yang jadwal penggantinya belum ada menahan penyimpanan; server
        // memeriksa hal yang sama. Peringatannya baru muncul setelah tombol
        // simpan ditekan — memilih "Ya" saja belum keliru, admin memang sedang
        // menuju tombol pensilnya.
        function belumTerjadwal() {
            return Array.prototype.filter.call(
                form.querySelectorAll('[data-replacement-toggle]'),
                function (select) {
                    return select.value === 'ya' && select.dataset.hasRequest === '0';
                }
            );
        }

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
                // Tautan ke murid pertama; sisanya lewat pensil di barisnya.
                guardLink.href = tertunda[0].dataset.scheduleUrl;
            }

            return tertunda;
        }

        form.querySelectorAll('[data-replacement-toggle]').forEach(function (select) {
            select.addEventListener('change', function () {
                var link = select.parentElement.querySelector('[data-replacement-edit]');
                if (link) {
                    link.classList.toggle('d-none', select.value !== 'ya');
                }
                perbaruiPenjaga();
            });
        });

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
    });
});
</script>
@endpush
