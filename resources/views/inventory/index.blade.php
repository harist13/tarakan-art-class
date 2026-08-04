@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Inventaris Barang</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#stockModal"><i class="bi bi-arrow-left-right"></i> Stock In/Out</button>
        <a href="{{ route('inventory.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Tambah Barang</a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Daftar Barang</span>
                <form method="GET" data-live class="d-flex" style="max-width:260px;">
                    <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm me-2" placeholder="Cari barang...">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th>Kode</th><th>Nama</th><th>Harga Beli</th><th>Harga Jual</th><th>Stok</th><th class="text-end">Aksi</th></tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                <tr>
                                    <td class="fw-bold">{{ $item->item_code }}</td>
                                    <td>{{ $item->item_name }}</td>
                                    <td>Rp {{ number_format($item->purchase_price, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($item->selling_price, 0, ',', '.') }}</td>
                                    <td><span class="badge bg-{{ $item->remaining_stock > 0 ? 'success' : 'danger' }}">{{ $item->remaining_stock }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('inventory.edit', $item) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('inventory.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus barang ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">Belum ada barang.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $items->links() }}
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Pergerakan Stok Terbaru</div>
            <div class="card-body">
                @forelse($movements as $mv)
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-bold small">{{ $mv->item->item_name ?? '-' }}</div>
                            <div class="text-muted" style="font-size:.75rem;">{{ $mv->movement_date->format('d M Y') }}</div>
                        </div>
                        <span class="badge bg-{{ $mv->type === 'in' ? 'success' : 'warning' }}">
                            {{ $mv->type === 'in' ? '+' : '-' }}{{ $mv->quantity }}
                        </span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada pergerakan stok.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Stock Movement Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('inventory.movement') }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Catat Pergerakan Stok</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Barang</label>
                        <select name="inventory_item_id" id="movementItem" class="form-select" required>
                            <option value="">— Pilih Barang —</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-price="{{ $item->purchase_price }}" data-selling="{{ $item->selling_price }}">{{ $item->item_name }} (stok: {{ $item->remaining_stock }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Tipe</label>
                            <select name="type" id="movementType" class="form-select"><option value="in">Stock In (masuk)</option><option value="out">Stock Out (keluar/jual)</option></select>
                        </div>
                        <div class="col-6 mb-3"><label class="form-label">Jumlah</label>
                            <input type="number" min="1" name="quantity" id="movementQty" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label">Tanggal</label>
                        <input type="date" name="movement_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    {{-- Hanya relevan untuk stok masuk: retur/koreksi stok tidak mengeluarkan uang. --}}
                    <div class="alert alert-light border py-2 mb-0" id="purchaseBox">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="is_purchase" value="1" id="isPurchase" checked>
                            <label class="form-check-label small" for="isPurchase">
                                Ini pembelian (restok) — catat sebagai <strong>pengeluaran</strong> di Laporan Keuangan
                                <span class="d-block text-muted" id="purchaseHint">Perkiraan: —</span>
                                <span class="d-block text-muted">Hapus centang bila stok masuk dari retur atau koreksi hitung stok.</span>
                            </label>
                        </div>
                    </div>
                    {{-- Hanya relevan untuk stok keluar: barang rusak/hilang/dipakai sendiri tidak menghasilkan uang. --}}
                    <div class="alert alert-light border py-2 mb-0" id="saleBox">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="is_sale" value="1" id="isSale" checked>
                            <label class="form-check-label small" for="isSale">
                                Ini penjualan — catat sebagai <strong>pemasukan</strong> di Laporan Keuangan
                                <span class="d-block text-muted" id="saleHint">Perkiraan: —</span>
                                <span class="d-block text-muted">Hapus centang bila barang rusak, hilang, atau dipakai sendiri untuk kegiatan kelas.</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Stok masuk memunculkan opsi "ini pembelian" (harga beli), stok keluar
    // memunculkan opsi "ini penjualan" (harga jual), lengkap dengan perkiraan
    // nilainya sebelum disimpan.
    (function () {
        const type = document.getElementById('movementType');
        const item = document.getElementById('movementItem');
        const qty = document.getElementById('movementQty');
        const purchaseBox = document.getElementById('purchaseBox');
        const saleBox = document.getElementById('saleBox');
        const purchaseHint = document.getElementById('purchaseHint');
        const saleHint = document.getElementById('saleHint');

        if (!type || !purchaseBox || !saleBox) return;

        const rupiah = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

        function hitung(price, jumlah) {
            return (price > 0 && jumlah > 0)
                ? 'Perkiraan: ' + jumlah + ' x ' + rupiah(price) + ' = ' + rupiah(price * jumlah)
                : 'Perkiraan: — (pilih barang & isi jumlah)';
        }

        function sync() {
            const isIn = type.value === 'in';
            purchaseBox.classList.toggle('d-none', !isIn);
            saleBox.classList.toggle('d-none', isIn);

            const option = item.options[item.selectedIndex];
            const jumlah = parseInt(qty.value, 10) || 0;

            purchaseHint.textContent = hitung(parseFloat(option ? option.dataset.price : 0) || 0, jumlah);
            saleHint.textContent = hitung(parseFloat(option ? option.dataset.selling : 0) || 0, jumlah);
        }

        [type, item, qty].forEach((el) => {
            el.addEventListener('change', sync);
            el.addEventListener('input', sync);
        });
        sync();
    })();
</script>
@endpush
