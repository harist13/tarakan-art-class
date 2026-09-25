@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1 text-gray-800 fw-bold">Tagihan gabungan</h1>
        <p class="text-muted small mb-0">Satukan beberapa invoice satu keluarga (mis. kakak-adik) supaya orang tua cukup sekali bayar lewat satu tautan.</p>
    </div>
    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke pembayaran</a>
</div>

@error('payment_ids')
    <div class="alert alert-danger small"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
@enderror

{{-- ─── Gabungan yang sudah dibuat ─────────────────────────────────── --}}
@if($bundles->isNotEmpty())
<div class="card mb-4">
    <div class="card-header fw-bold"><i class="bi bi-collection me-2 text-primary"></i>Tagihan gabungan yang sudah dibuat</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-muted small text-uppercase">
                    <tr><th>Kode</th><th>Murid</th><th>Invoice</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach($bundles as $bundle)
                        @php
                            $status = match (true) {
                                $bundle->isPaid() => ['bg' => '#15803D', 'label' => 'Lunas'],
                                $bundle->cancelled_at !== null => ['bg' => '#475569', 'label' => 'Dibatalkan'],
                                $bundle->awaitingGateway() => ['bg' => '#0891B2', 'label' => 'Menunggu bayar'],
                                default => ['bg' => 'rgba(245, 136, 12, 1)', 'label' => 'Belum dibayar'],
                            };
                            $sisa = $bundle->isPaid()
                                ? (int) $bundle->payments->sum(fn ($p) => (int) round((float) $p->payment_amount))
                                : $bundle->totalDue();
                        @endphp
                        <tr>
                            <td class="fw-bold text-nowrap">{{ $bundle->code }}
                                <div class="small text-muted fw-normal">{{ $bundle->created_at->format('d M Y') }}</div>
                            </td>
                            <td>{{ $bundle->studentNames() }}</td>
                            <td class="small">
                                @foreach($bundle->payments as $p)
                                    <div class="text-nowrap {{ $p->payment_status === 'paid' && ! $bundle->isPaid() ? 'text-decoration-line-through text-muted' : '' }}"
                                         @if($p->payment_status === 'paid' && ! $bundle->isPaid()) title="Sudah dibayar terpisah — tidak ikut ditagih lagi" @endif>
                                        {{ $p->invoice_number }} · {{ $p->student->name ?? '-' }}
                                    </div>
                                @endforeach
                            </td>
                            <td class="text-end fw-semibold text-nowrap">Rp {{ number_format($sisa, 0, ',', '.') }}</td>
                            <td><span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: {{ $status['bg'] }};">{{ $status['label'] }}</span></td>
                            <td class="text-end text-nowrap">
                                @if($bundle->isOpen())
                                    @php $wa = $bundle->guardian()?->whatsappNumber(); @endphp
                                    @if($wa)
                                        <a href="{{ route('payment-bundles.whatsapp', $bundle) }}" target="_blank" rel="noopener" class="btn btn-sm btn-whatsapp" title="Kirim tagihan gabungan via WhatsApp (+{{ $wa }})"><i class="bi bi-whatsapp"></i></a>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Nomor HP wali belum terisi / tidak valid"><i class="bi bi-whatsapp"></i></button>
                                    @endif
                                    @if($midtransActive)
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Salin tautan pembayaran" data-copy-link="{{ $bundle->payUrl() }}"><i class="bi bi-link-45deg"></i></button>
                                    @endif
                                    @if($midtransActive && $bundle->awaitingGateway())
                                        <form action="{{ route('payment-bundles.sync-gateway', $bundle) }}" method="POST" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-outline-secondary" title="Cek status pembayaran ke Midtrans"><i class="bi bi-arrow-repeat"></i></button>
                                        </form>
                                    @endif
                                    <form action="{{ route('payment-bundles.destroy', $bundle) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Batalkan tagihan gabungan {{ $bundle->code }}? Invoice di dalamnya tetap bisa dibayar satu per satu.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Batalkan gabungan"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                @elseif($bundle->isPaid())
                                    <a href="{{ $bundle->payUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Lihat bukti pembayaran"><i class="bi bi-receipt-cutoff"></i></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ─── Keluarga yang bisa digabung ───────────────────────────────── --}}
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold"><i class="bi bi-people me-2 text-primary"></i>Keluarga dengan lebih dari satu tagihan belum lunas</span>
        @if($groups->isNotEmpty())
            <div class="input-group input-group-sm" style="width:240px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="groupSearch" class="form-control border-start-0 ps-0 py-2" placeholder="Cari wali / murid...">
            </div>
        @endif
    </div>
    <div class="card-body">
        <p class="small text-muted">
            <i class="bi bi-info-circle me-1"></i>Murid dianggap satu keluarga bila <strong>nomor WhatsApp wali</strong> atau
            <strong>nama wali</strong>-nya sama. Centang invoice yang ingin digabung — minimal dua. Invoice aslinya tidak berubah
            dan tetap bisa dibayar sendiri-sendiri.
        </p>

        @forelse($groups as $group)
            <form action="{{ route('payment-bundles.store') }}" method="POST" class="border rounded p-3 mb-3 bundle-group"
                  data-search="{{ mb_strtolower($group['guardian'].' '.$group['students']->pluck('name')->implode(' ')) }}">
                @csrf
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="fw-bold">{{ $group['guardian'] }}</span>
                    @foreach($group['students'] as $s)
                        <span class="badge bg-light text-dark border rounded-pill">
                            {{ $s->name }}
                            @if($group['name_only'])<span class="fw-normal text-muted ms-1">{{ $s->phone_number ?: 'tanpa nomor' }}</span>@endif
                        </span>
                    @endforeach
                    @if($group['name_only'])
                        {{-- Tersambung lewat nama wali saja; nomornya disebut satu per satu
                             supaya admin bisa langsung menilai apakah ini satu keluarga. --}}
                        <span class="badge rounded-pill text-dark" style="background-color:#FEF3C7;"
                              title="Dikelompokkan karena nama walinya sama. Nomor wali: {{ $group['students']->map(fn ($s) => $s->name.' '.($s->phone_number ?: '-'))->implode(', ') }}">
                            <i class="bi bi-exclamation-triangle me-1"></i>Nomor WhatsApp wali berbeda — pastikan satu keluarga
                        </span>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <tbody>
                            @foreach($group['payments'] as $p)
                                @php $lamaGabung = $p->openBundle(); @endphp
                                <tr>
                                    <td style="width:2rem;">
                                        <input class="form-check-input bundle-check" type="checkbox" name="payment_ids[]" value="{{ $p->id }}"
                                               id="pb{{ $p->id }}" data-amount="{{ (int) round((float) $p->payment_amount) }}" @checked(! $lamaGabung)>
                                    </td>
                                    <td><label for="pb{{ $p->id }}" class="mb-0">
                                        <span class="fw-semibold">{{ $p->invoice_number }}</span> · {{ $p->student->name }}
                                        @if($p->periodLabel())<span class="text-muted small">· {{ $p->periodLabel() }}</span>@endif
                                    </label>
                                        @if($lamaGabung)
                                            <div class="small text-muted"><i class="bi bi-collection me-1"></i>Sudah di {{ $lamaGabung->code }} — mencentangnya akan membatalkan {{ $lamaGabung->code }}.</div>
                                        @endif
                                    </td>
                                    <td class="small text-nowrap text-muted">
                                        Jatuh tempo {{ $p->due_date?->format('d M Y') ?? '-' }}
                                        @if($p->isOverdue())<span class="badge bg-danger ms-1">Lewat {{ $p->daysOverdue() }} hari</span>@endif
                                    </td>
                                    <td class="text-end text-nowrap fw-semibold">Rp {{ number_format((float) $p->payment_amount, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-end align-items-center gap-3">
                    <span class="small">Total dipilih: <strong class="bundle-total">Rp 0</strong></span>
                    <button type="submit" class="btn btn-sm btn-primary bundle-submit"><i class="bi bi-collection me-1"></i>Gabungkan</button>
                </div>
            </form>
        @empty
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                Tidak ada keluarga dengan lebih dari satu tagihan yang belum lunas.
            </div>
        @endforelse
        <p class="text-muted small mb-0 d-none" id="groupEmpty">Tidak ada keluarga yang cocok dengan pencarian.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rupiah = n => 'Rp ' + n.toLocaleString('id-ID');

    // Total & tombol per keluarga mengikuti centang — gabungan butuh minimal dua invoice.
    document.querySelectorAll('.bundle-group').forEach(function (form) {
        const checks = form.querySelectorAll('.bundle-check');
        const total = form.querySelector('.bundle-total');
        const submit = form.querySelector('.bundle-submit');

        function refresh() {
            const dipilih = Array.from(checks).filter(c => c.checked);
            total.textContent = rupiah(dipilih.reduce((sum, c) => sum + Number(c.dataset.amount), 0));
            submit.disabled = dipilih.length < 2;
            submit.innerHTML = '<i class="bi bi-collection me-1"></i>Gabungkan ' + dipilih.length + ' invoice';
            submit.title = dipilih.length < 2 ? 'Pilih minimal dua invoice' : '';
        }

        checks.forEach(c => c.addEventListener('change', refresh));
        refresh();
    });

    // Cari keluarga berdasarkan nama wali / murid.
    const search = document.getElementById('groupSearch');
    if (search) {
        search.addEventListener('input', function () {
            const q = search.value.trim().toLowerCase();
            let tampil = 0;
            document.querySelectorAll('.bundle-group').forEach(function (form) {
                const cocok = !q || form.dataset.search.includes(q);
                form.classList.toggle('d-none', !cocok);
                if (cocok) tampil++;
            });
            document.getElementById('groupEmpty').classList.toggle('d-none', tampil > 0);
        });
    }

    // Salin tautan pembayaran — sama dengan di daftar pembayaran.
    document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const link = btn.dataset.copyLink;
            const done = function () {
                const icon = btn.querySelector('i');
                icon.className = 'bi bi-check-lg';
                setTimeout(function () { icon.className = 'bi bi-link-45deg'; }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(link).then(done);
                return;
            }
            const area = document.createElement('textarea');
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
});
</script>
@endpush
