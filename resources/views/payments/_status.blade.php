{{-- Lencana status invoice. Dipakai baris tabel (desktop) & kartu (mobile),
     jadi keduanya tidak pernah bisa saling berbeda. --}}
<div class="d-flex flex-wrap gap-1 {{ $statusAlign ?? '' }}">
    <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: {{ $payment->payment_status === 'paid' ? '#15803D' : 'rgba(245, 136, 12, 1)' }};">
        {{ $payment->payment_status === 'paid' ? 'Paid' : 'Unpaid' }}
    </span>

    @if($payment->payment_status === 'paid' && $payment->gateway_payment_type)
        <span class="badge rounded-pill px-3 py-1 text-white fw-semibold text-nowrap" style="background-color: #0891B2;" title="Dibayar online lewat Midtrans">
            <i class="bi bi-credit-card me-1"></i>{{ str_replace('_', ' ', $payment->gateway_payment_type) }}
        </span>
    @elseif($payment->awaitingGateway())
        <span class="badge rounded-pill px-3 py-1 text-white fw-semibold text-nowrap" style="background-color: #475569;" title="Tautan pembayaran sudah dibuka, menunggu dana masuk">
            <i class="bi bi-hourglass-split me-1"></i>Menunggu bayar
        </span>
    @endif

    @if($payment->isOverdue())
        <span class="badge rounded-pill px-3 py-1 text-white fw-semibold text-nowrap" style="background-color: #DC2626;">
            <i class="bi bi-clock-history me-1"></i>Lewat {{ $payment->daysOverdue() }} hari
        </span>
    @endif
</div>
