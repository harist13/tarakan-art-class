@extends('layouts.app')

@push('styles')
<style>
    /* Nomor langkah di kepala kartu. Halaman ini punya urutan yang harus
       diikuti — periode dulu, baru murid, baru rincian — dan tanpa penanda
       urutan admin cenderung mengisi kartu tengah lebih dulu lalu bingung
       kenapa daftar muridnya berubah saat periode diganti. */
    .step-badge {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.6rem; height: 1.6rem; flex-shrink: 0;
        border-radius: 50%; font-size: .85rem; font-weight: 700;
        background: var(--bs-primary); color: #fff;
    }
    /* Footer ringkasan & tombol terbit. Sengaja TIDAK menempel (sticky):
       bilah yang ikut bergerak saat menggulir mengambil ruang layar terus-
       menerus dan menutupi baris paling bawah tabel. Tombolnya berada di ujung
       daftar, dan pelipatan 25 baris menjaga jaraknya tetap terjangkau.

       Latarnya --surface (putih), sama dengan badan & kepala kartunya —
       .card-header di tema ini juga memakai --surface, jadi ketiga bagian
       kartu berwarna sama dan footer tidak terbaca sebagai kotak terpisah. */
    .issue-bar {
        background-color: var(--surface);
        border-top: 1px solid var(--border);
        border-radius: 0 0 1rem 1rem;
        padding: 1.25rem 1.5rem;
    }
    /* Baris yang nominalnya kosong ditandai sebelum disimpan, bukan sesudah
       ditolak server. */
    tr.row-empty-amount { background-color: var(--bs-warning-bg-subtle); }
</style>
@endpush

@section('content')
@php
    $periodLabel = \App\Models\Payment::labelForPeriod($period);
    $total = collect($billable)->sum('amount');

    // Batas baris yang ditampilkan lebih dulu. Sisanya dibuka satu klik,
    // BUKAN dipaginasi: centang murid hidup di DOM, jadi berpindah halaman
    // akan menghapus pilihan di halaman sebelumnya — padahal seluruh guna
    // tabel ini adalah mengirim puluhan invoice dalam satu kali kirim.
    // Baris yang terlipat tetap ada di form dan tetap ikut terkirim.
    $pickLimit = 25;
    $skipLimit = 10;
@endphp

<div class="d-sm-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Buat Invoice</h1>
    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

{{-- Satu kalimat pembuka. Halaman ini menggantikan dua menu lama, jadi admin
     yang terbiasa mencari "Tagihan Bulanan" perlu tahu ia sudah ada di sini. --}}
<p class="text-muted mb-4">
    Menerbitkan invoice untuk <strong>satu murid</strong> maupun <strong>seluruh murid sekaligus</strong> —
    bedanya hanya berapa banyak yang Anda centang di langkah 2.
</p>

{{-- ─── LANGKAH 1 ─────────────────────────────────────────────────
     Wajib berdiri sendiri sebagai form GET, dan itulah sebabnya rincian
     invoice TIDAK bisa ikut ditaruh di sini: mengganti jenis atau bulan
     memuat ulang halaman untuk menghitung ulang siapa yang perlu ditagih,
     sementara rincian invoice justru harus bertahan sampai disimpan.
     HTML tidak mengizinkan form bersarang, jadi keduanya memang terpisah.

     Isinya diringkas jadi satu baris — cuma dua pilihan dan satu kotak
     bulan, tidak sepadan dengan satu kartu penuh. --}}
<form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="step-badge">1</span>
        <span class="fw-semibold text-nowrap">Jenis tagihan</span>

        <div class="btn-group btn-group-sm" role="group" aria-label="Jenis tagihan">
            <input type="radio" class="btn-check" name="lepas" value="0" id="jenisSpp"
                   @checked(! $lepas) onchange="this.form.submit()">
            <label class="btn btn-outline-primary" for="jenisSpp">SPP bulanan</label>

            <input type="radio" class="btn-check" name="lepas" value="1" id="jenisLepas"
                   @checked($lepas) onchange="this.form.submit()">
            <label class="btn btn-outline-primary" for="jenisLepas">Tagihan lepas</label>
        </div>

        {{-- Dimatikan saat tagihan lepas: tagihan lepas memang tidak berperiode. --}}
        <input type="month" name="period" value="{{ $period ?? \App\Models\Payment::periodFor() }}"
               class="form-control form-control-sm" style="width:170px;"
               aria-label="Bulan yang akan ditagih"
               @disabled($lepas) onchange="this.form.submit()">

        {{-- Penjelasan mengikuti pilihan yang sedang aktif, bukan menampilkan
             keduanya sekaligus — halaman memuat ulang tiap kali diganti, jadi
             teks ini selalu bicara tentang mode yang sedang berlaku. --}}
        <span class="text-muted small ms-auto" style="max-width:34rem;">
            @if($lepas)
                Biaya di luar SPP: pendaftaran, kelas tambahan, dan sejenisnya. Tanpa periode, jadi boleh
                lebih dari satu untuk murid yang sama dalam bulan yang sama.
            @else
                Satu invoice per murid per bulan. Murid yang sudah ditagih {{ $periodLabel }} otomatis dilewati.
            @endif
        </span>

        <noscript>
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i> Terapkan</button>
        </noscript>
    </div>
</form>

{{-- ─── LANGKAH 2 ─────────────────────────────────────────────────
     Rincian invoice, daftar murid, dan tombol terbit dalam satu kartu &
     satu form POST. Ringkasannya jadi footer kartu, bukan kartu tersendiri:
     angka "8 murid · Rp 4.650.000" menjelaskan tabel tepat di atasnya, dan
     dulu ia duduk terpisah seolah benda lain. --}}
<form action="{{ route('payments.store') }}" method="POST" id="invoiceForm">
    @csrf
    @unless($lepas)
        <input type="hidden" name="billing_period" value="{{ $period }}">
    @endunless

    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2 flex-wrap">
            <span class="step-badge">2</span>
            <span class="fw-semibold">Buat invoice</span>
            <span class="text-muted small">
                @if($lepas)
                    Semua murid aktif — centang yang perlu ditagih.
                @else
                    Untuk periode <strong>{{ $periodLabel }}</strong>.
                @endif
            </span>
        </div>

        @if(empty($billable))
            <div class="card-body">
                @if($lepas)
                    <p class="text-muted mb-0">Belum ada murid aktif.</p>
                @else
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle me-1"></i>
                        <strong>Semua sudah ditagih untuk {{ $periodLabel }}.</strong>
                        Tidak ada invoice baru yang perlu diterbitkan periode ini — daftar di bawah menjelaskan
                        setiap muridnya. Ganti bulan di langkah 1 untuk menagih periode lain.
                    </div>
                @endif
            </div>
        @else
            <div class="card-body">
                {{-- Rincian ditaruh di atas sebagai kepala pengaturan, bukan
                     langkah tersendiri: keempatnya hampir selalu dibiarkan pada
                     nilai bawaan, sedangkan pekerjaan yang sesungguhnya —
                     mencentang murid & menyetel nominal — ada di bawah. --}}
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tanggal Invoice</label>
                        <input type="date" name="payment_date" class="form-control"
                               value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                        <small class="text-muted">Kapan invoice diterbitkan.</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control"
                               value="{{ old('due_date', \App\Models\Payment::defaultDueDate()) }}" required>
                        <small class="text-muted">Lewat tanggal ini, murid ditandai menunggak.</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Metode / Channel</label>
                        {{-- Bawaannya QRIS: invoice biasanya dikirim lewat WhatsApp
                             dan dibayar sendiri oleh orang tua, bukan tunai di meja. --}}
                        @include('payments._method-select', [
                            'selected' => old('payment_method', 'qris'),
                        ])
                        <small class="text-muted">Catatan awal — orang tua tetap bebas memilih saat membayar.</small>
                    </div>
                    {{-- Status bawaannya Unpaid. Paid tetap boleh untuk berapa pun
                         murid — orang tua yang bayar tunai atau transfer manual di
                         meja admin memang harus bisa dicatat sekali jalan. Yang
                         dijaga bukan opsinya, melainkan "Pilih semua" yang tidak
                         sengaja tersimpan sebagai Paid: peringatan hidup di bawah
                         dan konfirmasi sebelum simpan menyebut angkanya. --}}
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status</label>
                        <select name="payment_status" id="invoiceStatus" class="form-select" required>
                            <option value="unpaid" @selected(old('payment_status', 'unpaid') === 'unpaid')>Unpaid — menagih</option>
                            <option value="paid" @selected(old('payment_status') === 'paid')>Paid — uang sudah diterima</option>
                        </select>
                        <small class="text-muted">Hampir selalu Unpaid.</small>
                    </div>
                </div>

                <div class="alert alert-light border small mb-0" id="hintUnpaid">
                    <i class="bi bi-info-circle me-1"></i>
                    Invoice <strong>Unpaid</strong> belum tercatat sebagai pemasukan. Kirim ke wali murid lewat tombol
                    <i class="bi bi-whatsapp"></i> di daftar pembayaran; statusnya berubah lunas sendiri setelah
                    dibayar lewat Midtrans, atau lewat tombol <i class="bi bi-check2-circle"></i> Lunas untuk tunai.
                    Catatan per invoice bisa ditambahkan lewat Edit setelah terbit.
                </div>
                {{-- Peringatan muncul saat Status diubah ke Paid, bukan menunggu
                     sampai admin menekan tombol simpan. Letaknya di atas tabel
                     supaya terbaca sebelum mencentang, bukan sesudah. --}}
                <div class="alert alert-warning small mb-0 d-none" id="hintPaid">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>Uang dinyatakan sudah diterima.</strong> Invoice langsung tercatat sebagai pemasukan di
                    Laporan Keuangan &amp; Dashboard begitu disimpan. Pilih ini hanya untuk pembayaran yang benar-benar
                    sudah di tangan — bukan untuk menagih.
                </div>

                <hr class="my-4">

                <h2 class="h6 fw-semibold mb-3">Murid yang akan ditagih</h2>

                @if($lepas)
                    <div class="alert alert-light border small">
                        <i class="bi bi-info-circle me-1"></i>
                        Sengaja <strong>tidak ada yang tercentang</strong> dan <strong>nominalnya kosong</strong> —
                        tagihan lepas selalu untuk beberapa orang tertentu dengan nominal tersendiri, bukan biaya kelas
                        bulanan. Murid yang belum punya kelas berbiaya ikut tampil, karena justru itulah kasus utamanya:
                        biaya pendaftaran.
                    </div>
                @endif

                {{-- Pencarian berdiri di barisnya sendiri: digabung dengan ringkasan
                     & "pilih semua", barisnya patah tidak keruan di layar sedang. --}}
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div class="input-group input-group-sm" style="width:260px;">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" id="pickSearch" class="form-control border-start-0 ps-0"
                               placeholder="Cari nama atau ID murid..." autocomplete="off">
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="pickToggleAll" @checked($preselect)>
                        <label class="form-check-label small text-nowrap" for="pickToggleAll" id="pickToggleAllLabel">Pilih semua</label>
                    </div>
                    <span class="text-muted small ms-auto" id="pickShown">{{ count($billable) }} murid</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:44px;"></th>
                                <th>Murid</th>
                                <th>Kelas</th>
                                <th class="text-end" style="width:210px;">Nominal (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($billable as $row)
                                @php
                                    $student = $row['student'];
                                    // Tagihan lepas tidak pernah sebesar biaya kelas. Mengisinya
                                    // otomatis di sini mengundang invoice "biaya pendaftaran"
                                    // senilai SPP hanya karena angkanya sudah terlanjur ada.
                                    $prefill = $lepas ? '' : (int) $row['amount'];
                                    // Uang pendaftaran hanya menempel di invoice pertama murid,
                                    // jadi nominal barisnya bisa lebih besar dari SPP-nya sendiri.
                                    // Selisih yang tak dijelaskan akan terbaca sebagai salah hitung.
                                    $pendaftaran = $row['registration'] ?? 0;
                                    $iuran = $row['amount'] - $pendaftaran;
                                    // Bulan pertama bisa lebih murah dari biaya kelas karena
                                    // murid masuk di pertengahan bulan. Sama seperti uang
                                    // pendaftaran, selisihnya disebutkan — bukan dibiarkan
                                    // terbaca sebagai salah hitung.
                                    $potongan = $row['discount'] ?? 0;
                                @endphp
                                {{-- Baris di atas batas dilipat sejak dari server, bukan
                                     oleh JS sesudah halaman tampil: kalau tidak, seluruh
                                     daftar sempat berkedip sebelum terlipat. --}}
                                <tr data-name="{{ Str::lower($student->name.' '.$student->student_id) }}"
                                    @class(['d-none' => $loop->index >= $pickLimit])>
                                    <td>
                                        <input class="form-check-input pick" type="checkbox"
                                               name="students[]" value="{{ $student->id }}"
                                               @checked($preselect) aria-label="Sertakan {{ $student->name }}">
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $student->name }}</div>
                                        <small class="text-muted">{{ $student->student_id }}</small>
                                    </td>
                                    <td class="small text-muted">
                                        {{ $student->classes->pluck('class_category')->implode(', ') ?: 'Belum ada kelas' }}
                                    </td>
                                    <td>
                                        <input type="number" step="1000" min="0" placeholder="0"
                                               name="amounts[{{ $student->id }}]"
                                               class="form-control form-control-sm text-end pick-amount"
                                               value="{{ old('amounts.'.$student->id, $prefill) }}">
                                        @if($lepas && $iuran > 0)
                                            <small class="text-muted d-block text-end mt-1">
                                                Biaya kelas: Rp {{ number_format($iuran, 0, ',', '.') }}
                                            </small>
                                        @endif
                                        @if($potongan > 0)
                                            <small class="d-block text-end mt-1 text-success-emphasis">
                                                <i class="bi bi-dash-circle me-1"></i>Harga bulan pertama
                                                Minggu ke-{{ $row['start_week'] }} — hemat
                                                Rp {{ number_format($potongan, 0, ',', '.') }} dari biaya kelas penuh.
                                            </small>
                                        @endif
                                        @if($pendaftaran > 0)
                                            <small class="d-block text-end mt-1 text-warning-emphasis">
                                                <i class="bi bi-plus-circle me-1"></i>Termasuk uang pendaftaran
                                                Rp {{ number_format($pendaftaran, 0, ',', '.') }} — invoice pertama murid ini.
                                            </small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p id="pickNoMatch" class="text-center text-muted my-3 d-none">Tidak ada murid yang cocok dengan pencarian.</p>
                </div>

                {{-- Murid yang terlipat TETAP tercentang dan tetap ikut terkirim —
                     tombol ini hanya membuka tampilannya, bukan memuat data baru. --}}
                <div class="text-center mt-3 {{ count($billable) > $pickLimit ? '' : 'd-none' }}" id="pickMoreWrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pickMore">
                        <i class="bi bi-chevron-down me-1"></i>
                        <span id="pickMoreLabel">Tampilkan {{ max(0, count($billable) - $pickLimit) }} murid lainnya</span>
                    </button>
                </div>
            </div>

            {{-- Ringkasan & tombol terbit sebagai footer kartu: ia menjelaskan
                 tabel tepat di atasnya. Menempel saat digulir supaya angka yang
                 menentukan keputusan ada di tempat keputusan diambil. --}}
            <div class="card-footer issue-bar d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">
                        <span id="pickCount">{{ $preselect ? count($billable) : 0 }}</span> murid dipilih
                        <span id="pickHidden" class="text-warning small fw-normal"></span>
                    </div>
                    <div class="text-muted small">
                        Total nilai invoice:
                        <strong id="pickTotal">Rp {{ number_format($preselect ? $total : 0, 0, ',', '.') }}</strong>
                    </div>
                    <div class="text-danger small d-none" id="pickEmptyWarn"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" id="issueButton">
                    <i class="bi bi-receipt me-1"></i> <span id="issueLabel">Terbitkan Invoice</span>
                </button>
            </div>
        @endif
    </div>
</form>

{{-- Daftar yang tidak ditagih sama pentingnya dengan daftar yang ditagih:
     tanpa ini, "murid tidak muncul di tabel" tidak bisa dibedakan dari
     "murid terlupakan". --}}
@unless($lepas)
    <div class="card mb-4">
        <div class="card-header">
            Tidak ditagih untuk {{ $periodLabel }} — <span id="skipShown">{{ count($skipped) }}</span> murid
            <span class="text-muted small ms-1">alasan tiap murid ada di kanan</span>
        </div>
        <div class="card-body">
            @if(! empty($skipped))
                {{-- Pencarian ikut menjangkau alasannya, bukan cuma nama: mengetik
                     "Unpaid" langsung menyaring siapa saja yang sudah ditagih tapi
                     uangnya belum masuk. --}}
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div class="input-group input-group-sm" style="width:260px;">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" id="skipSearch" class="form-control border-start-0 ps-0"
                               placeholder="Cari murid, nomor invoice, atau alasan..." autocomplete="off">
                    </div>
                    <span class="text-muted small">
                        <span class="text-success">&#9679;</span> sudah lunas
                        <span class="text-danger ms-2">&#9679;</span> sudah ditagih, belum dibayar
                    </span>
                </div>
            @endif

            @forelse($skipped as $row)
                @php
                    // Warna datang dari Student::billingSkip(), bukan dari
                    // mencocokkan kata pada kalimat alasannya.
                    $toneClass = match ($row['tone']) {
                        'paid' => 'text-success fw-semibold',
                        'unpaid' => 'text-danger fw-semibold',
                        default => 'text-muted',
                    };
                @endphp
                <div class="skip-row d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom @if($loop->index >= $skipLimit) d-none @endif"
                     data-skip="{{ Str::lower($row['student']->name.' '.$row['student']->student_id.' '.$row['reason']) }}">
                    <div>
                        <span class="fw-semibold">{{ $row['student']->name }}</span>
                        <small class="text-muted ms-1">{{ $row['student']->student_id }}</small>
                    </div>
                    <small class="{{ $toneClass }}">{{ $row['reason'] }}</small>
                </div>
            @empty
                <p class="text-muted mb-0">Tidak ada murid yang dilewati.</p>
            @endforelse

            <p id="skipNoMatch" class="text-center text-muted mt-3 mb-0 d-none">Tidak ada yang cocok dengan pencarian.</p>

            <div class="text-center mt-3 {{ count($skipped) > $skipLimit ? '' : 'd-none' }}" id="skipMoreWrap">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="skipMore">
                    <i class="bi bi-chevron-down me-1"></i>
                    <span id="skipMoreLabel">Tampilkan {{ max(0, count($skipped) - $skipLimit) }} murid lainnya</span>
                </button>
            </div>
        </div>
    </div>
@endunless
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('invoiceForm');
        const picks = Array.from(document.querySelectorAll('.pick'));

        if (!form || !picks.length) return;

        const LIMIT = {{ $pickLimit }};

        const countEl = document.getElementById('pickCount');
        const totalEl = document.getElementById('pickTotal');
        const hiddenEl = document.getElementById('pickHidden');
        const emptyEl = document.getElementById('pickEmptyWarn');
        const shownEl = document.getElementById('pickShown');
        const toggleAll = document.getElementById('pickToggleAll');
        const toggleLabel = document.getElementById('pickToggleAllLabel');
        const search = document.getElementById('pickSearch');
        const noMatch = document.getElementById('pickNoMatch');
        const moreWrap = document.getElementById('pickMoreWrap');
        const moreBtn = document.getElementById('pickMore');
        const moreLabel = document.getElementById('pickMoreLabel');
        const status = document.getElementById('invoiceStatus');
        const hintUnpaid = document.getElementById('hintUnpaid');
        const hintPaid = document.getElementById('hintPaid');
        const issueLabel = document.getElementById('issueLabel');
        const issueButton = document.getElementById('issueButton');

        let query = '';
        let expanded = picks.length <= LIMIT;

        const rupiah = n => 'Rp ' + n.toLocaleString('id-ID');
        const rowOf = p => p.closest('tr');
        const fieldOf = p => rowOf(p).querySelector('.pick-amount');
        const amountOf = p => Number(fieldOf(p).value || 0);
        const isBlank = p => fieldOf(p).value.trim() === '';
        const chosen = () => picks.filter(p => p.checked);

        // Cocok dengan pencarian. Baris yang TERLIPAT tetap "dalam jangkauan":
        // melipat cuma menyembunyikan tampilan dan barisnya tetap ikut terkirim
        // — beda dengan pencarian, yang memang menyaring.
        const inScope = p => query === '' || (rowOf(p).dataset.name || '').includes(query);

        function layout() {
            let cocok = 0;

            picks.forEach(function (p) {
                const ok = inScope(p);
                if (ok) cocok++;
                const terlipat = ok && !expanded && cocok > LIMIT;
                rowOf(p).classList.toggle('d-none', !ok || terlipat);
            });

            const sisa = Math.max(0, cocok - LIMIT);
            moreWrap.classList.toggle('d-none', expanded || sisa === 0);
            moreLabel.textContent = `Tampilkan ${sisa} murid lainnya`;
            noMatch.classList.toggle('d-none', cocok > 0);
            shownEl.textContent = query === ''
                ? `${picks.length} murid`
                : `${cocok} dari ${picks.length} murid`;
        }

        function refresh() {
            const picked = chosen();
            const total = picked.reduce((sum, p) => sum + amountOf(p), 0);
            const paid = status && status.value === 'paid';

            countEl.textContent = picked.length;
            totalEl.textContent = rupiah(total);

            // Tercentang tapi tersaring keluar oleh pencarian: disebutkan, sebab
            // menyimpan lebih banyak daripada yang terlihat adalah kejutan buruk.
            // Baris yang sekadar terlipat tidak dihitung di sini — ia masih
            // bagian dari daftar yang sedang dilihat, hanya belum dibuka.
            const tersaring = picked.filter(p => !inScope(p)).length;
            hiddenEl.textContent = tersaring ? `(${tersaring} di antaranya tidak tampil karena pencarian)` : '';

            // Baris tanpa nominal ditandai sekarang, bukan setelah ditolak server.
            picks.forEach(function (p) {
                rowOf(p).classList.toggle('row-empty-amount', p.checked && isBlank(p));
            });
            const kosong = picked.filter(isBlank).length;
            emptyEl.classList.toggle('d-none', kosong === 0);
            emptyEl.textContent = kosong ? `${kosong} baris tercentang belum diisi nominalnya.` : '';

            // Tombol menyebut apa yang akan terjadi, bukan sekadar "Terbitkan".
            if (issueLabel) {
                issueLabel.textContent = picked.length === 0
                    ? (paid ? 'Catat pembayaran' : 'Terbitkan invoice')
                    : (paid
                        ? `Catat ${picked.length} pembayaran · ${rupiah(total)}`
                        : `Terbitkan ${picked.length} invoice · ${rupiah(total)}`);
            }
            if (issueButton) {
                issueButton.classList.toggle('btn-warning', paid);
                issueButton.classList.toggle('btn-primary', !paid);
            }
            if (hintPaid && hintUnpaid) {
                hintPaid.classList.toggle('d-none', !paid);
                hintUnpaid.classList.toggle('d-none', paid);
            }

            // "Pilih semua" mengenai seluruh baris yang lolos pencarian, termasuk
            // yang masih terlipat — melipat bukan menyaring.
            const dalamJangkauan = picks.filter(inScope);
            const terpilih = dalamJangkauan.filter(p => p.checked).length;
            toggleAll.checked = dalamJangkauan.length > 0 && terpilih === dalamJangkauan.length;
            toggleAll.indeterminate = terpilih > 0 && terpilih < dalamJangkauan.length;
            toggleLabel.textContent = query === ''
                ? 'Pilih semua'
                : `Pilih ${dalamJangkauan.length} hasil pencarian`;
        }

        function render() {
            layout();
            refresh();
        }

        picks.forEach(p => p.addEventListener('change', refresh));
        document.querySelectorAll('.pick-amount').forEach(a => a.addEventListener('input', refresh));
        if (status) status.addEventListener('change', refresh);

        search.addEventListener('input', function () {
            query = (search.value || '').trim().toLowerCase();
            render();
        });

        moreBtn.addEventListener('click', function () {
            expanded = true;
            render();
        });

        toggleAll.addEventListener('change', function () {
            picks.filter(inScope).forEach(function (p) { p.checked = toggleAll.checked; });
            refresh();
        });

        form.addEventListener('submit', function (e) {
            const picked = chosen();

            if (!picked.length) {
                e.preventDefault();
                alert('Belum ada murid yang dicentang di langkah 2.');
                return;
            }

            const kosong = picked.filter(isBlank);
            if (kosong.length) {
                e.preventDefault();
                // Baris bermasalahnya bisa saja sedang terlipat — dibuka dulu
                // supaya penandaan kuningnya benar-benar terlihat.
                expanded = true;
                render();
                alert(`${kosong.length} murid tercentang belum diisi nominalnya. Baris yang perlu diisi ditandai kuning.`);
                fieldOf(kosong[0]).focus();
                return;
            }

            const total = rupiah(picked.reduce((sum, p) => sum + amountOf(p), 0));

            // Status Paid berarti uang dinyatakan SUDAH diterima dan langsung
            // masuk Laporan Keuangan. Angkanya disebut supaya "Pilih semua"
            // yang tertinggal di Paid tidak lolos tanpa disadari.
            const pesan = status && status.value === 'paid'
                ? `Mencatat ${picked.length} pemasukan senilai ${total} sebagai SUDAH DITERIMA.\n\nLanjutkan?`
                : `Terbitkan ${picked.length} invoice senilai ${total}?`;

            if (!confirm(pesan)) e.preventDefault();
        });

        render();
    })();

    // Daftar "Tidak ditagih". Berdiri sendiri dari tabel murid di atas:
    // keduanya dua daftar berbeda, dan menyaring atau membuka salah satunya
    // tidak boleh ikut mengubah yang lain.
    (function () {
        const search = document.getElementById('skipSearch');
        const rows = Array.from(document.querySelectorAll('.skip-row'));

        if (!search || !rows.length) return;

        const LIMIT = {{ $skipLimit }};
        const shown = document.getElementById('skipShown');
        const noMatch = document.getElementById('skipNoMatch');
        const moreWrap = document.getElementById('skipMoreWrap');
        const moreBtn = document.getElementById('skipMore');
        const moreLabel = document.getElementById('skipMoreLabel');

        let query = '';
        let expanded = rows.length <= LIMIT;

        function render() {
            let cocok = 0;
            const terlihat = [];

            rows.forEach(function (row) {
                const ok = query === '' || (row.dataset.skip || '').includes(query);
                if (ok) cocok++;
                const terlipat = ok && !expanded && cocok > LIMIT;
                row.classList.toggle('d-none', !ok || terlipat);
                if (ok && !terlipat) terlihat.push(row);
            });

            const sisa = Math.max(0, cocok - LIMIT);
            moreWrap.classList.toggle('d-none', expanded || sisa === 0);
            moreLabel.textContent = `Tampilkan ${sisa} murid lainnya`;
            shown.textContent = query === '' ? rows.length : `${cocok} dari ${rows.length}`;
            noMatch.classList.toggle('d-none', cocok > 0);

            // Garis pemisah dilepas dari baris terlihat yang paling bawah, kalau
            // tidak, daftarnya berakhir dengan garis menggantung — dan baris
            // terakhir berpindah-pindah mengikuti pencarian & pelipatan.
            rows.forEach(r => r.classList.add('border-bottom'));
            if (terlihat.length) terlihat[terlihat.length - 1].classList.remove('border-bottom');
        }

        search.addEventListener('input', function () {
            query = (search.value || '').trim().toLowerCase();
            render();
        });

        moreBtn.addEventListener('click', function () {
            expanded = true;
            render();
        });

        render();
    })();
</script>
@endpush
