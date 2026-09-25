{{-- Potongan baris tagihan gabungan di Daftar transaksi pembayaran. Dipakai
     baris tabel (desktop) & kartu (mobile) lewat $part, supaya keduanya
     tidak pernah menyimpang — sama seperti _status & _actions untuk invoice. --}}
@php
    $items = $bundle->unpaidPayments();
    $jatuhTempo = $items->pluck('due_date')->filter()->min();
    $telat = $items->max(fn ($p) => $p->daysOverdue());
@endphp

@switch($part)
    {{-- Invoice di dalamnya, masing-masing tetap bisa dibuka (edit / void /
         lunas tunai dilakukan per invoice). --}}
    @case('students')
        @foreach($items as $p)
            <div class="{{ $loop->last ? '' : 'mb-1' }}">
                <div>{{ $p->student->name ?? '-' }}</div>
                <small class="text-muted text-nowrap">
                    <a href="{{ route('payments.edit', $p) }}" class="text-muted" title="Buka invoice {{ $p->invoice_number }}">{{ $p->invoice_number }}</a>
                    @if($p->periodLabel()) · {{ $p->periodLabel() }} @endif
                    · Rp {{ number_format($p->payment_amount, 0, ',', '.') }}
                </small>
            </div>
        @endforeach
        @break

    @case('due')
        {{ $jatuhTempo?->format('d M Y') ?? '-' }}
        @break

    @case('status')
        <div class="d-flex flex-wrap gap-1 {{ $statusAlign ?? '' }}">
            @if($bundle->awaitingGateway())
                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold text-nowrap" style="background-color: #475569;" title="Tautan gabungan sudah dibuka, menunggu dana masuk">
                    <i class="bi bi-hourglass-split me-1"></i>Menunggu bayar
                </span>
            @else
                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: rgba(245, 136, 12, 1);">Unpaid</span>
            @endif
            <a href="{{ route('payment-bundles.index') }}" class="badge rounded-pill px-3 py-1 text-white fw-semibold text-nowrap text-decoration-none" style="background-color: #6D28D9;"
               title="Satu tautan bayar untuk {{ $items->count() }} invoice">
                <i class="bi bi-collection me-1"></i>{{ $bundle->code }}
            </a>
            @if($telat > 0)
                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold text-nowrap" style="background-color: #DC2626;">
                    <i class="bi bi-clock-history me-1"></i>Lewat {{ $telat }} hari
                </span>
            @endif
        </div>
        @break

    @case('actions')
        <div class="d-flex flex-wrap gap-1 align-items-center {{ $actionsAlign ?? 'justify-content-end' }}">
            @php $wa = $bundle->guardian()?->whatsappNumber(); @endphp
            @if($wa)
                <a href="{{ route('payment-bundles.whatsapp', $bundle) }}" target="_blank" rel="noopener" class="btn btn-sm btn-whatsapp"
                   title="Kirim tagihan gabungan via WhatsApp (+{{ $wa }})"><i class="bi bi-whatsapp"></i></a>
            @else
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Nomor HP wali belum terisi / tidak valid"><i class="bi bi-whatsapp"></i></button>
            @endif
            @if($midtransActive)
                <button type="button" class="btn btn-sm btn-outline-primary" title="Salin tautan pembayaran gabungan" data-copy-link="{{ $bundle->payUrl() }}">
                    <i class="bi bi-link-45deg"></i>
                </button>
            @endif
            @if($midtransActive && $bundle->awaitingGateway())
                <form action="{{ route('payment-bundles.sync-gateway', $bundle) }}" method="POST">
                    @csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-secondary" title="Cek status pembayaran ke Midtrans"><i class="bi bi-arrow-repeat"></i></button>
                </form>
            @endif
            <form action="{{ route('payment-bundles.destroy', $bundle) }}" method="POST"
                  onsubmit="return confirm('Batalkan {{ $bundle->code }}? Invoice di dalamnya kembali ditagih satu per satu.')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" title="Batalkan gabungan — invoicenya kembali tampil sendiri-sendiri"><i class="bi bi-x-lg"></i></button>
            </form>
        </div>
        @break

    {{-- Penanda untuk pemantau pelunasan: satu per invoice, supaya halaman
         menyegar sendiri begitu gabungan ini lunas lewat notifikasi Midtrans. --}}
    @case('watch')
        @foreach($items as $p)
            <span hidden data-payment-id="{{ $p->id }}" data-payment-status="{{ $p->payment_status }}"
                  data-payment-invoice="{{ $p->invoice_number }}" data-payment-student="{{ $p->student->name ?? '' }}"></span>
        @endforeach
        @break
@endswitch
