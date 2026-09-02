@extends('layouts.app')

@section('content')
@php
    // Query aktif tanpa nomor halaman, dipakai untuk tombol pindah periode.
    $filterParams = collect(request()->query())->except('page')->all();
    $currentMonth = now()->format('Y-m');
    $isAllPeriods = $month === '';
@endphp

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Laporan Keuangan</h1>
        <div class="small text-muted mt-1">
            <i class="bi bi-calendar3 me-1"></i>
            @if($isAllPeriods)
                Menampilkan <span class="fw-semibold">seluruh periode</span> — seluruh bulan ikut terhitung.
            @else
                Menampilkan periode <span class="fw-semibold">{{ $periodLabel }}</span> — transaksi di luar bulan ini tidak ikut terhitung.
            @endif
            @if($canViewSummary && in_array($type, ['income', 'expense'], true))
                <span class="d-block">Filter tipe hanya menyaring tabel; ringkasan di bawah tetap menghitung pemasukan &amp; pengeluaran periode ini.</span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2">
        @include('partials.export-buttons', ['route' => 'export.financials'])
        <a href="{{ route('financials.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Catat Transaksi</a>
    </div>
</div>

{{-- Ringkasan nominal keuangan hanya untuk Super Admin. --}}
@if($canViewSummary)
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
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Rincian Transaksi</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:190px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari kategori...">
            </div>
            <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" style="width:160px;"
                   title="Kosongkan untuk menampilkan semua periode">
            @if($isAllPeriods)
                <a href="{{ route('financials.index', array_merge($filterParams, ['month' => $currentMonth])) }}"
                   class="btn btn-sm btn-outline-primary text-nowrap" title="Kembali ke bulan berjalan">
                    <i class="bi bi-calendar-event"></i> Bulan Ini
                </a>
            @else
                <a href="{{ route('financials.index', array_merge($filterParams, ['month' => ''])) }}"
                   class="btn btn-sm btn-outline-primary text-nowrap" title="Tampilkan seluruh periode">
                    <i class="bi bi-calendar-range"></i> Semua Bulan
                </a>
            @endif
            <select name="type" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua</option>
                <option value="income" @selected($type === 'income')>Pemasukan</option>
                <option value="expense" @selected($type === 'expense')>Pengeluaran</option>
            </select>
            @if($search !== '' || $type !== '' || ! $isAllPeriods)
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
                            <td>
                                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: {{ $trx->type === 'income' ? '#15803D' : 'rgba(245, 136, 12, 1)' }};">
                                    {{ $trx->type === 'income' ? 'Masuk' : 'Keluar' }}
                                </span>
                            </td>
                            <td>
                                {{ $trx->category }}
                                @if($trx->payment_id)
                                    <span class="badge bg-light text-dark border ms-1 rounded-pill px-2 py-1 fw-semibold" title="Tercatat otomatis dari invoice lunas"><i class="bi bi-lightning-charge-fill"></i> Dari Invoice</span>
                                @elseif($trx->inventory_item_id)
                                    <span class="badge bg-light text-dark border ms-1 rounded-pill px-2 py-1 fw-semibold" title="Tercatat otomatis saat barang ditambahkan"><i class="bi bi-lightning-charge-fill"></i> Dari Inventaris</span>
                                @endif
                            </td>
                            <td class="small">{{ $trx->description ?: '-' }}</td>
                            <td class="fw-bold">Rp {{ number_format($trx->amount, 0, ',', '.') }}</td>
                            <td class="text-end">
                                @if($trx->payment_id)
                                    <a href="{{ route('payments.index', ['search' => $trx->payment?->invoice_number]) }}"
                                       class="btn btn-sm btn-outline-secondary" title="Kelola dari menu Pembayaran">
                                        <i class="bi bi-receipt-cutoff"></i> Invoice
                                    </a>
                                @else
                                    @if($trx->inventory_item_id)
                                        <a href="{{ route('inventory.index', ['search' => $trx->inventoryItem?->item_code]) }}"
                                           class="btn btn-sm btn-outline-secondary" title="Lihat barangnya di Inventaris">
                                            <i class="bi bi-box-seam"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('financials.edit', $trx) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('financials.destroy', $trx) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus transaksi ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">
                            @if($isAllPeriods)
                                Belum ada transaksi yang cocok dengan filter.
                            @else
                                Belum ada transaksi pada periode {{ $periodLabel }}.
                                <a href="{{ route('financials.index', array_merge($filterParams, ['month' => ''])) }}">Lihat semua bulan</a>.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $transactions->links() }}
    </div>
</div>
@endsection
