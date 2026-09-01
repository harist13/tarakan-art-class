{{-- Lencana status invoice. Dipakai baris tabel (desktop) & kartu (mobile),
     jadi keduanya tidak pernah bisa saling berbeda. --}}
<div class="d-flex flex-wrap gap-1 {{ $statusAlign ?? '' }}">
    <span class="badge {{ $payment->payment_status === 'paid' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' }} rounded-pill px-2 py-1">
        {{ $payment->payment_status === 'paid' ? 'Paid' : 'Unpaid' }}
    </span>

    @if($payment->payment_status === 'paid' && $payment->gateway_payment_type)
        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2 py-1 text-nowrap" title="Dibayar online lewat Midtrans">
            <i class="bi bi-credit-card me-1"></i>{{ str_replace('_', ' ', $payment->gateway_payment_type) }}
        </span>
    @elseif($payment->awaitingGateway())
        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 text-nowrap" title="Tautan pembayaran sudah dibuka, menunggu dana masuk">
            <i class="bi bi-hourglass-split me-1"></i>Menunggu bayar
        </span>
    @endif

    @if($payment->isOverdue())
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 text-nowrap">
            <i class="bi bi-clock-history me-1"></i>Lewat {{ $payment->daysOverdue() }} hari
        </span>
    @endif
</div>
