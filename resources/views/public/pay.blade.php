@extends('layouts.public')

@section('title', 'Pembayaran '.$payment->invoice_number)
@section('description', 'Halaman pembayaran invoice '.$payment->invoice_number.' Tarakan Art Class.')

@php
    // Halaman ini dibuka orang tua dari tautan WhatsApp, jadi jangan sampai
    // terindeks mesin pencari walau tokennya sudah acak.
    $paid = $state === 'paid';
    $rows = array_filter([
        'No. Invoice' => $payment->invoice_number,
        'Nama murid' => $payment->student->name ?? '-',
        'Tanggal invoice' => $payment->payment_date->format('d F Y'),
        'Jatuh tempo' => $payment->due_date?->format('d F Y'),
        'Dibayar pada' => $paid ? $payment->paid_at?->format('d F Y H:i') : null,
    ]);
@endphp

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<x-site.section tone="paper-2">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="tac-card p-4 p-md-5">

                <div class="text-center mb-4">
                    <span class="tac-eyebrow">{{ config('site.name') }}</span>
                    <h1 class="fs-3 lh-sm mt-2 mb-2 tac-text-ink">
                        {{ $paid ? 'Pembayaran Diterima' : 'Pembayaran Kelas Seni' }}
                    </h1>
                    <p class="tac-muted mb-0">
                        @if($paid)
                            Terima kasih, invoice ini sudah lunas.
                        @else
                            Halo {{ $payment->student->parent_name ?: 'Bapak/Ibu' }}, berikut tagihan yang perlu diselesaikan.
                        @endif
                    </p>
                </div>

                <div class="tac-dashed-box p-4 mb-4">
                    <dl class="row mb-0 small">
                        @foreach($rows as $label => $value)
                            <dt class="col-5 fw-semibold tac-text-ink">{{ $label }}</dt>
                            <dd class="col-7 mb-2 tac-muted text-end">{{ $value }}</dd>
                        @endforeach
                    </dl>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold tac-text-ink">Total tagihan</span>
                        <span class="fs-4 fw-bold {{ $paid ? 'tac-text-leaf' : 'tac-text-coral' }}">
                            Rp {{ number_format((float) $payment->payment_amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                @if($paid)
                    <div class="text-center">
                        <p class="tac-muted small mb-4">
                            Simpan halaman ini sebagai bukti pembayaran. Kirimkan juga ke admin agar
                            pembayaran Anda langsung dicek dan dicatat.
                        </p>
                        {{-- Bootstrap Icons tidak dimuat di layout publik, jadi logo WhatsApp
                             memakai aset gambar — sama seperti tombol mengambang & footer. --}}
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            @if($adminWaUrl)
                                <x-site.btn :href="$adminWaUrl" variant="coral" target="_blank" rel="noopener"
                                            class="d-inline-flex align-items-center gap-2">
                                    <img src="{{ asset('images/whatsapp.png') }}" alt="" width="20" height="20" decoding="async">
                                    Kirim bukti ke Admin
                                </x-site.btn>
                            @endif
                            <x-site.btn :href="route('public.home')" variant="ghost">Kembali ke beranda</x-site.btn>
                        </div>
                    </div>

                @elseif($state === 'payable')
                    @if($payment->isOverdue())
                        <p class="text-center tac-text-coral small mb-3">
                            Invoice ini sudah lewat jatuh tempo {{ $payment->daysOverdue() }} hari.
                        </p>
                    @endif

                    <div class="d-grid gap-2">
                        <x-site.btn id="tac-pay" size="lg">Bayar Sekarang</x-site.btn>
                        {{-- Cadangan bila popup Snap gagal terbuka (pemblokir popup,
                             JavaScript mati): halaman pembayaran Midtrans langsung. --}}
                        <a href="{{ $redirectUrl }}" class="text-center small tac-muted text-decoration-underline">
                            Tombol tidak berfungsi? Buka halaman pembayaran
                        </a>
                    </div>

                    <p class="text-center tac-muted-soft small mt-4 mb-0">
                        Pembayaran diproses oleh Midtrans. Tersedia QRIS, Virtual Account bank,
                        dan e-wallet. Status invoice diperbarui otomatis setelah pembayaran berhasil.
                    </p>

                @elseif($state === 'unavailable')
                    <div class="text-center">
                        <p class="tac-muted mb-4">
                            Pembayaran online belum aktif. Silakan selesaikan pembayaran langsung
                            di studio atau hubungi admin lewat WhatsApp untuk instruksi transfer.
                        </p>
                        <x-site.btn href="https://wa.me/{{ config('site.contact.whatsapp') }}" variant="coral" target="_blank" rel="noopener">
                            Hubungi Admin
                        </x-site.btn>
                    </div>

                @else
                    <div class="text-center">
                        <p class="tac-muted mb-4">
                            Maaf, tautan pembayaran sedang tidak dapat dibuat. Silakan coba lagi
                            beberapa saat lagi atau hubungi admin kami.
                        </p>
                        <x-site.btn href="https://wa.me/{{ config('site.contact.whatsapp') }}" variant="coral" target="_blank" rel="noopener">
                            Hubungi Admin
                        </x-site.btn>
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-site.section>
@endsection

@if($state === 'payable')
    @push('scripts')
        <script src="{{ $snapJsUrl }}" data-client-key="{{ $clientKey }}"></script>
        <script>
            // Setelah Snap melapor sukses, server kita menanyakan ulang status
            // ke Midtrans sebelum halaman dimuat ulang. Tanpa langkah ini,
            // invoice baru berubah lunas saat webhook tiba — dan webhook tidak
            // pernah sampai bila aplikasi berjalan di localhost.
            //
            // onPending sengaja tidak memuat ulang: instruksi VA-nya masih
            // ditampilkan Snap di layar dan belum ada uang yang masuk.
            function tacVerifyThenReload() {
                fetch(@json(route('pay.verify', $payment->pay_token)), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                    },
                }).catch(function () {
                    // Gagal memverifikasi bukan alasan menahan halaman: admin
                    // masih punya tombol "cek status" di daftar pembayaran.
                }).then(function () {
                    window.location.reload();
                });
            }

            document.getElementById('tac-pay').addEventListener('click', function () {
                if (typeof window.snap === 'undefined') {
                    window.location.href = @json($redirectUrl);
                    return;
                }

                window.snap.pay(@json($snapToken), {
                    onSuccess: tacVerifyThenReload,
                    onError: function () { window.location.href = @json($redirectUrl); },
                });
            });
        </script>
    @endpush
@endif
