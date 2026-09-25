@extends('layouts.public')

@section('title', 'Pembayaran '.$bundle->code)
@section('description', 'Halaman pembayaran tagihan gabungan '.$bundle->code.' Tarakan Art Class.')
@section('robots', 'noindex, nofollow')

@php
    $paid = $state === 'paid';
    // Yang lunas ditampilkan semua sebagai tanda terima; yang belum lunas hanya
    // invoice yang masih ditagih — invoice yang sudah dibayar terpisah tidak
    // boleh terlihat seolah ikut ditagih lagi.
    $items = $paid ? $bundle->payments : $bundle->unpaidPayments();
    $total = $paid
        ? (int) $bundle->payments->sum(fn ($p) => (int) round((float) $p->payment_amount))
        : $bundle->totalDue();
    $guardian = $bundle->guardian();
    $overdue = ! $paid && $items->contains(fn ($p) => $p->isOverdue());
@endphp

@section('content')
<x-site.section tone="paper-2">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="tac-card p-4 p-md-5">

                <div class="text-center mb-4">
                    <span class="tac-eyebrow">{{ config('site.name') }}</span>
                    <h1 class="fs-3 lh-sm mt-2 mb-2 tac-text-ink">
                        {{ $paid ? 'Pembayaran diterima' : 'Pembayaran kelas seni' }}
                    </h1>
                    <p class="tac-muted mb-0">
                        @if($paid)
                            Terima kasih, seluruh tagihan untuk {{ $bundle->studentNames() }} sudah lunas.
                        @else
                            Halo {{ $guardian?->parent_name ?: 'Bapak/Ibu' }}, berikut tagihan gabungan untuk {{ $bundle->studentNames() }}.
                        @endif
                    </p>
                </div>

                <div class="tac-dashed-box p-4 mb-4">
                    @foreach($items as $payment)
                        <div class="d-flex justify-content-between align-items-start gap-3 small {{ $loop->last ? '' : 'mb-3 pb-3 border-bottom' }}">
                            <div>
                                <div class="fw-semibold tac-text-ink">{{ $payment->student->name ?? '-' }}</div>
                                <div class="tac-muted">
                                    {{ $payment->invoice_number }}
                                    @if($payment->periodLabel()) · {{ $payment->periodLabel() }} @endif
                                </div>
                                @if(! $paid && $payment->due_date)
                                    <div class="tac-muted-soft">Jatuh tempo {{ $payment->due_date->format('d F Y') }}</div>
                                @endif
                            </div>
                            <div class="fw-semibold tac-text-ink text-nowrap">
                                Rp {{ number_format((float) $payment->payment_amount, 0, ',', '.') }}
                            </div>
                        </div>
                    @endforeach

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold tac-text-ink">Total tagihan</span>
                        <span class="fs-4 fw-bold {{ $paid ? 'tac-text-leaf' : 'tac-text-coral' }}">
                            Rp {{ number_format($total, 0, ',', '.') }}
                        </span>
                    </div>
                    @if($paid && $bundle->paid_at)
                        <div class="small tac-muted text-end mt-1">Dibayar pada {{ $bundle->paid_at->format('d F Y H:i') }}</div>
                    @endif
                </div>

                @if($paid)
                    <div class="text-center">
                        <p class="tac-muted small mb-4">Simpan halaman ini sebagai bukti pembayaran.</p>
                        <x-site.btn :href="route('public.home')" variant="ghost">Kembali ke beranda</x-site.btn>
                    </div>

                @elseif($state === 'payable')
                    @if($overdue)
                        <div class="alert d-flex align-items-center gap-2 p-3 mb-3 rounded-4" style="background-color: #FEF3C7; border: 1px solid rgba(245, 136, 12, 0.4); color: #92400E;">
                            <span style="font-size: 1.2rem;" aria-hidden="true">⚠️</span>
                            <div class="small fw-semibold">Sebagian tagihan sudah lewat jatuh tempo. Harap segera menyelesaikan pembayaran.</div>
                        </div>
                    @endif

                    <div class="d-grid gap-2">
                        <x-site.btn id="tac-pay" size="lg">Bayar sekarang</x-site.btn>
                        <a href="{{ $redirectUrl }}" class="text-center small tac-muted text-decoration-underline">
                            Tombol tidak berfungsi? Buka halaman pembayaran
                        </a>
                    </div>

                    <p class="text-center tac-muted-soft small mt-4 mb-0">
                        Cukup sekali bayar untuk semua tagihan di atas. Pembayaran diproses oleh Midtrans lewat
                        Virtual Account seluruh bank, dan status tiap invoice diperbarui otomatis setelah berhasil.
                    </p>

                @elseif($state === 'cancelled')
                    <div class="text-center">
                        <p class="tac-muted mb-4">
                            Tautan tagihan gabungan ini sudah tidak berlaku. Silakan gunakan tautan
                            pembayaran terbaru dari admin, atau hubungi kami lewat WhatsApp.
                        </p>
                        <x-site.btn href="https://wa.me/{{ config('site.contact.whatsapp') }}" variant="coral" target="_blank" rel="noopener">
                            Hubungi admin
                        </x-site.btn>
                    </div>

                @elseif($state === 'unavailable')
                    <div class="text-center">
                        <p class="tac-muted mb-4">
                            Pembayaran online belum aktif. Silakan selesaikan pembayaran langsung
                            di studio atau hubungi admin lewat WhatsApp untuk instruksi transfer.
                        </p>
                        <x-site.btn href="https://wa.me/{{ config('site.contact.whatsapp') }}" variant="coral" target="_blank" rel="noopener">
                            Hubungi admin
                        </x-site.btn>
                    </div>

                @else
                    <div class="text-center">
                        <p class="tac-muted mb-4">
                            Maaf, tautan pembayaran sedang tidak dapat dibuat. Silakan coba lagi
                            beberapa saat lagi atau hubungi admin kami.
                        </p>
                        <x-site.btn href="https://wa.me/{{ config('site.contact.whatsapp') }}" variant="coral" target="_blank" rel="noopener">
                            Hubungi admin
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
            // Sama dengan halaman bayar biasa: status ditanyakan ulang ke Midtrans
            // dari server sebelum halaman dimuat ulang.
            function tacVerifyThenReload() {
                fetch(@json(route('pay.bundle.verify', $bundle->pay_token)), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                    },
                }).catch(function () {}).then(function () {
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
