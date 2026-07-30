@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Pembayaran & Invoice</h1>
    <div class="d-flex gap-2">
        @include('partials.export-buttons', ['route' => 'export.payments'])
        <a href="{{ route('payments.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-receipt"></i> Buat Invoice</a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Daftar Transaksi Pembayaran</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:200px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari invoice / murid...">
            </div>
            <select name="status" class="form-select form-select-sm" style="width:160px;">
                <option value="">Semua Status</option>
                <option value="paid" @selected($status === 'paid')>Paid</option>
                <option value="unpaid" @selected($status === 'unpaid')>Unpaid</option>
            </select>
            @if($search !== '' || $status !== '')
                <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Invoice</th><th>Murid</th><th>Tanggal</th><th>Jumlah</th><th>Metode</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td class="fw-bold">{{ $payment->invoice_number }}</td>
                            <td>{{ $payment->student->name ?? '-' }}</td>
                            <td>{{ $payment->payment_date->format('d M Y') }}</td>
                            <td>Rp {{ number_format($payment->payment_amount, 0, ',', '.') }}</td>
                            <td>
                                @php $methods = ['cash' => 'Cash', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'virtual_account' => 'Virtual Account']; @endphp
                                {{ $methods[$payment->payment_method] ?? ucfirst($payment->payment_method) }}
                            </td>
                            <td><span class="badge bg-{{ $payment->payment_status === 'paid' ? 'success' : 'warning' }}">{{ $payment->payment_status === 'paid' ? 'Paid' : 'Unpaid' }}</span></td>
                            <td class="text-end">
                                @if($payment->payment_status !== 'paid')
                                <form action="{{ route('payments.confirm', $payment) }}" method="POST" class="d-inline" onsubmit="return confirm('Konfirmasi invoice ini sebagai LUNAS?')">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-success" title="Konfirmasi Lunas"><i class="bi bi-check2-circle"></i> Lunas</button>
                                </form>
                                @endif
                                <a href="{{ route('payments.edit', $payment) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                @if(auth()->user()->isSuperAdmin())
                                <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="d-inline" onsubmit="return confirm('Void pembayaran ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="Void"><i class="bi bi-x-circle"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Belum ada pembayaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $payments->links() }}
    </div>
</div>
@endsection
