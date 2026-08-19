@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Pembayaran & Invoice</h1>
    <div class="d-flex flex-wrap gap-2">
        @include('partials.export-buttons', ['route' => 'export.payments'])
        {{-- Satu pintu untuk keduanya: centang satu murid untuk satu invoice,
             centang semua untuk sebulan penuh. Dulu ini dua tombol, dan karena
             keduanya menghasilkan baris invoice yang sama persis, yang tersisa
             hanyalah tebak-tebakan harus klik yang mana. --}}
        <a href="{{ route('payments.create') }}" class="btn btn-sm btn-primary shadow-sm text-nowrap"><i class="bi bi-receipt"></i> Buat Invoice</a>
    </div>
</div>

{{-- Peringatan konfigurasi ditaruh di atas daftar: kalau di bawah, ia baru
     terbaca setelah admin menggulir seluruh tabel — terlambat. --}}
@if($webhookUnreachable)
    <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Notifikasi pelunasan Midtrans tidak akan sampai.</strong>
        <code>APP_URL</code> menunjuk ke <code>{{ config('app.url') }}</code> — alamat lokal yang tidak ada di DNS
        publik (<code>localhost</code>, <code>.test</code>, <code>.local</code>, atau IP jaringan dalam), sehingga
        server Midtrans tidak bisa menjangkaunya. Invoice tidak akan berubah lunas sendiri meski pembayarannya berhasil.
        Jalankan tunnel (mis. ngrok), set <code>APP_URL</code> ke alamat publiknya, lalu daftarkan
        <code>{{ route('midtrans.notification') }}</code> sebagai
        <em>Payment Notification URL</em> di Dashboard Midtrans.
        <br>
        Sementara ini, pembayaran <strong>VA</strong> masih bisa diselesaikan lewat tombol
        <i class="bi bi-arrow-repeat"></i> cek status — tapi <strong>e-wallet (DANA/GoPay) tidak bisa</strong>,
        karena transaksinya hanya dapat ditelusuri lewat notifikasi.
    </div>
@endif

@unless($midtransActive)
    <div class="alert alert-light border small">
        <i class="bi bi-info-circle me-1"></i>
        Pembayaran online belum aktif — isi <code>MIDTRANS_SERVER_KEY</code> &amp; <code>MIDTRANS_CLIENT_KEY</code>
        di file <code>.env</code> agar tautan bayar ikut terkirim di pesan WhatsApp.
    </div>
@endunless

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-nowrap">Daftar Transaksi Pembayaran</span>
        {{-- Filter status lalu pencarian, selalu berdampingan dalam satu baris.
             flex-nowrap dipakai agar keduanya MENGECIL saat ruang menyempit,
             bukan patah ke baris berikutnya.

             Ukuran select dipasang di DIV pembungkusnya, bukan di <select>-nya:
             Tom Select mengganti select asli dengan wrapper selebar 100% dan
             hanya menyalin gaya inline dari select — ukuran yang dipasang di
             sini aman dari penggantian itu. --}}
        <form method="GET" data-live class="d-flex flex-nowrap align-items-center gap-2">
            <div style="flex:0 1 160px;">
                <select name="status" class="form-select form-select-sm w-100">
                    <option value="">Semua Status</option>
                    <option value="paid" @selected($status === 'paid')>Paid</option>
                    <option value="unpaid" @selected($status === 'unpaid')>Unpaid</option>
                    <option value="overdue" @selected($status === 'overdue')>Lewat jatuh tempo</option>
                </select>
            </div>
            <div class="input-group input-group-sm" style="flex:0 1 240px; min-width:140px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari invoice / murid...">
            </div>
            @if($search !== '' || $status !== '')
                <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary flex-shrink-0" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        {{-- ─── Layar lebar: tabel ─────────────────────────────────────
             Kolom Tanggal & Metode dilebur ke kolom tetangganya sebagai baris
             kecil. Delapan kolom membuat tabel lebih lebar dari kartunya, dan
             yang terpotong justru kolom Aksi di ujung kanan. --}}
        <div class="table-responsive d-none d-xl-block">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Murid</th>
                        <th class="text-end">Jumlah</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>
                                <div class="fw-bold text-nowrap">{{ $payment->invoice_number }}</div>
                                <small class="text-muted text-nowrap">{{ $payment->payment_date->format('d M Y') }}</small>
                            </td>
                            {{-- Murid + periode ditampilkan berdampingan karena
                                 pasangan itulah yang unik: satu invoice per
                                 murid per bulan. Invoice tanpa periode adalah
                                 tagihan lepas dan sengaja tidak diberi label. --}}
                            <td>
                                <div>{{ $payment->student->name ?? '-' }}</div>
                                @if($payment->periodLabel())
                                    <small class="text-muted text-nowrap">Periode {{ $payment->periodLabel() }}</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="fw-semibold text-nowrap">Rp {{ number_format($payment->payment_amount, 0, ',', '.') }}</div>
                                <small class="text-muted text-nowrap">{{ $payment->methodLabel() }}</small>
                            </td>
                            <td class="text-nowrap">{{ $payment->due_date?->format('d M Y') ?? '-' }}</td>
                            <td>@include('payments._status')</td>
                            <td>@include('payments._actions')</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pembayaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ─── Layar sempit: kartu ────────────────────────────────────
             Tabel enam kolom pun tidak terbaca di ponsel; menggesernya ke
             samping menyembunyikan kolom Aksi. Datanya disusun menurun. --}}
        <div class="d-xl-none d-flex flex-column gap-3">
            @forelse($payments as $payment)
                <div class="border rounded-3 p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        {{-- min-width:0 wajib agar text-truncate bekerja di dalam flex. --}}
                        <div style="min-width:0">
                            <div class="fw-bold">{{ $payment->invoice_number }}</div>
                            <div class="text-truncate">{{ $payment->student->name ?? '-' }}</div>
                            @if($payment->periodLabel())
                                <small class="text-muted">Periode {{ $payment->periodLabel() }}</small>
                            @endif
                        </div>
                        @include('payments._status', ['statusAlign' => 'justify-content-end'])
                    </div>

                    <div class="d-flex justify-content-between align-items-end gap-2 flex-wrap mb-3">
                        <div>
                            <div class="fs-5 fw-bold">Rp {{ number_format($payment->payment_amount, 0, ',', '.') }}</div>
                            <small class="text-muted">{{ $payment->methodLabel() }}</small>
                        </div>
                        <small class="text-muted text-nowrap">
                            <i class="bi bi-calendar-event me-1"></i>
                            Jatuh tempo {{ $payment->due_date?->format('d M Y') ?? '-' }}
                        </small>
                    </div>

                    @include('payments._actions', ['actionsAlign' => 'justify-content-start'])
                </div>
            @empty
                <p class="text-center text-muted mb-0 py-4">Belum ada pembayaran.</p>
            @endforelse
        </div>

        <div class="mt-3">{{ $payments->links() }}</div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Salin tautan pembayaran. clipboard API hanya tersedia di konteks aman
    // (https/localhost), jadi ada cadangan lewat textarea + execCommand.
    document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var link = btn.dataset.copyLink;
            var done = function () {
                var icon = btn.querySelector('i');
                icon.className = 'bi bi-check-lg';
                setTimeout(function () { icon.className = 'bi bi-link-45deg'; }, 1500);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(link).then(done);
                return;
            }

            var area = document.createElement('textarea');
            area.value = link;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
            done();
        });
    });
</script>
@endpush
