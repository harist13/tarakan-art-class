{{-- Tombol aksi satu baris invoice. Dipakai baris tabel (desktop) & kartu
     (mobile) — dipisah ke sini supaya keduanya tidak pernah menyimpang.
     Tombol yang tampil menyesuaikan keadaan invoice, bukan ditampilkan semua
     lalu dimatikan: kolom Aksi sudah padat. --}}
<div class="d-flex flex-wrap gap-1 align-items-center {{ $actionsAlign ?? 'justify-content-end' }}">

    {{-- Kirim invoice ke WhatsApp wali murid. Dimatikan (bukan disembunyikan)
         saat nomornya belum bisa dipakai, supaya admin tahu ada data murid
         yang perlu dilengkapi. --}}
    @php $waNumber = $payment->student?->whatsappNumber(); @endphp
    @if($waNumber)
        <a href="{{ route('payments.whatsapp', $payment) }}" target="_blank" rel="noopener"
           class="btn btn-sm btn-whatsapp"
           title="Kirim invoice via WhatsApp ke {{ $payment->student->parent_name ?: $payment->student->name }} (+{{ $waNumber }})">
            <i class="bi bi-whatsapp"></i>
        </a>
    @else
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                title="Nomor HP wali murid belum terisi / tidak valid">
            <i class="bi bi-whatsapp"></i>
        </button>
    @endif

    {{-- Bukti pembayaran: halaman tanda terima yang sama persis dengan yang
         dilihat orang tua. Hanya untuk invoice lunas — membuka tautan invoice
         yang belum lunas justru menerbitkan transaksi Snap atas nama admin. --}}
    @if($payment->payment_status === 'paid')
        <a href="{{ $payment->payUrl() }}" target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-success"
           title="Lihat bukti pembayaran{{ $payment->gateway_transaction_id ? ' (Midtrans '.$payment->gateway_transaction_id.')' : '' }}">
            <i class="bi bi-receipt-cutoff"></i>
        </a>
    @endif

    {{-- Salin tautan Snap — untuk dikirim lewat kanal lain (SMS, chat orang
         tua yang bukan nomor terdaftar). Menyalin, bukan membuka, jadi tidak
         menerbitkan transaksi apa pun. --}}
    @if($midtransActive && $payment->payment_status !== 'paid')
        <button type="button" class="btn btn-sm btn-outline-primary" title="Salin tautan pembayaran"
                data-copy-link="{{ $payment->payUrl() }}">
            <i class="bi bi-link-45deg"></i>
        </button>
    @endif

    {{-- Jaring pengaman bila notifikasi Midtrans tidak sampai. --}}
    @if($midtransActive && $payment->awaitingGateway())
        <form action="{{ route('payments.sync-gateway', $payment) }}" method="POST">
            @csrf @method('PATCH')
            <button class="btn btn-sm btn-outline-secondary" title="Cek status pembayaran ke Midtrans">
                <i class="bi bi-arrow-repeat"></i>
            </button>
        </form>
    @endif

    {{-- Konfirmasi lunas manual hanya untuk pembayaran tunai. Untuk channel
         gateway, pelunasan datang dari Midtrans; tombolnya tetap ditampilkan
         (dimatikan) supaya admin tahu tombol itu ada dan tahu sebabnya —
         menyembunyikannya hanya memindahkan pertanyaan ke grup WhatsApp. --}}
    @if($payment->payment_status !== 'paid')
        @if($payment->canConfirmManually())
            <form action="{{ route('payments.confirm', $payment) }}" method="POST"
                  onsubmit="return confirm('Konfirmasi invoice ini sebagai LUNAS?')">
                @csrf @method('PATCH')
                {{-- Label "Lunas" disembunyikan di layar sempit; ikonnya sudah cukup
                     jelas dan tooltipnya tetap ada. --}}
                <button class="btn btn-sm btn-success text-nowrap" title="Konfirmasi lunas (pembayaran tunai)">
                    <i class="bi bi-check2-circle"></i><span class="d-none d-sm-inline ms-1">Lunas</span>
                </button>
            </form>
        @else
            {{-- Tooltip dipasang di span pembungkus: peramban tidak menampilkan
                 title milik elemen yang disabled. --}}
            <span class="d-inline-block" tabindex="0"
                  title="Hanya pembayaran Cash yang bisa dilunaskan manual. {{ $payment->methodLabel() }} menunggu konfirmasi dari Midtrans — pakai tombol cek status, atau ubah metodenya lewat Edit bila uangnya diterima tunai.">
                <button class="btn btn-sm btn-success text-nowrap" disabled>
                    <i class="bi bi-check2-circle"></i><span class="d-none d-sm-inline ms-1">Lunas</span>
                </button>
            </span>
        @endif
    @endif

    <a href="{{ route('payments.edit', $payment) }}" class="btn btn-sm btn-info text-white" title="Edit">
        <i class="bi bi-pencil"></i>
    </a>

    @if(auth()->user()->isSuperAdmin())
        <form action="{{ route('payments.destroy', $payment) }}" method="POST"
              onsubmit="return confirm('Void pembayaran ini?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-danger" title="Void"><i class="bi bi-x-circle"></i></button>
        </form>
    @endif
</div>
