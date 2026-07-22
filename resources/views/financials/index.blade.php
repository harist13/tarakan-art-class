@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Laporan Keuangan</h1>
    <div class="d-flex gap-2">
        @include('partials.export-buttons', ['route' => 'export.financials'])
        <a href="{{ route('financials.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Catat Transaksi</a>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-left-success h-100 py-2"><div class="card-body">
            <div class="text-success text-uppercase mb-1" style="font-size:.7rem;font-weight:800;">Pemasukan</div>
            <div class="h5 fw-bold mb-0">Rp {{ number_format($totalIncome, 0, ',', '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-left-warning h-100 py-2"><div class="card-body">
            <div class="text-warning text-uppercase mb-1" style="font-size:.7rem;font-weight:800;">Pengeluaran</div>
            <div class="h5 fw-bold mb-0">Rp {{ number_format($totalExpense, 0, ',', '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-left-primary h-100 py-2"><div class="card-body">
            <div class="text-primary text-uppercase mb-1" style="font-size:.7rem;font-weight:800;">Saldo (Profit/Loss)</div>
            <div class="h5 fw-bold mb-0 {{ $balance < 0 ? 'text-danger' : '' }}">Rp {{ number_format($balance, 0, ',', '.') }}</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Rincian Transaksi</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:190px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari kategori...">
            </div>
            <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" style="width:160px;">
            <select name="type" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua</option>
                <option value="income" @selected($type === 'income')>Pemasukan</option>
                <option value="expense" @selected($type === 'expense')>Pengeluaran</option>
            </select>
            @if($search !== '' || $type !== '')
                <a href="{{ route('financials.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Tanggal</th><th>Tipe</th><th>Kategori</th><th>Deskripsi</th><th>Jumlah</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                        <tr>
                            <td>{{ $trx->transaction_date->format('d M Y') }}</td>
                            <td><span class="badge bg-{{ $trx->type === 'income' ? 'success' : 'warning' }}">{{ $trx->type === 'income' ? 'Masuk' : 'Keluar' }}</span></td>
                            <td>{{ $trx->category }}</td>
                            <td class="small">{{ $trx->description ?: '-' }}</td>
                            <td class="fw-bold">Rp {{ number_format($trx->amount, 0, ',', '.') }}</td>
                            <td class="text-end">
                                <a href="{{ route('financials.edit', $trx) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('financials.destroy', $trx) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus transaksi ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Belum ada transaksi pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $transactions->links() }}
    </div>
</div>
@endsection
