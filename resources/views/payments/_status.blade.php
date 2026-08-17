{{-- Lencana status invoice. Dipakai baris tabel (desktop) & kartu (mobile),
     jadi keduanya tidak pernah bisa saling berbeda. --}}
<div class="d-flex flex-wrap gap-1 {{ $statusAlign ?? '' }}">
    <span class="badge bg-{{ $payment->payment_status === 'paid' ? 'success' : 'warning' }}">
        {{ $payment->payment_status === 'paid' ? 'Paid' : 'Unpaid' }}
    </span>

    @if($payment->payment_status === 'paid' && $payment->gateway_payment_type)
        <span class="badge bg-info text-nowrap" title="Dibayar online lewat Midtrans">
            <i class="bi bi-credit-card me-1"></i>{{ str_replace('_', ' ', $payment->gateway_payment_type) }}
        </span>
    @elseif($payment->awaitingGateway())
        <span class="badge bg-secondary text-nowrap" title="Tautan pembayaran sudah dibuka, menunggu dana masuk">
            <i class="bi bi-hourglass-split me-1"></i>Menunggu bayar
        </span>
    @endif

    @if($payment->isOverdue())
        <span class="badge bg-danger text-nowrap">Lewat {{ $payment->daysOverdue() }} hari</span>
    @endif
</div>
