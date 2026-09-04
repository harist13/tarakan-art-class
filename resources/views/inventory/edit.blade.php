@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Edit barang — {{ $item->item_code }}</h1>
    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        <form action="{{ route('inventory.update', $item) }}" method="POST">
            @csrf @method('PUT')
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Nama barang</label>
                    <input type="text" name="item_name" class="form-control" value="{{ old('item_name', $item->item_name) }}" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Stok saat ini</label>
                    <input type="number" class="form-control" value="{{ $item->remaining_stock }}" disabled>
                    <small class="text-muted">Ubah stok lewat menu Stock In/Out.</small></div>
                <div class="col-md-6 mb-3"><label class="form-label">Harga beli (Rp)</label>
                    <input type="number" step="500" min="0" name="purchase_price" class="form-control" value="{{ old('purchase_price', $item->purchase_price) }}" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Harga jual (Rp)</label>
                    <input type="number" step="500" min="0" name="selling_price" class="form-control" value="{{ old('selling_price', $item->selling_price) }}" required></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Perbarui</button>
        </form>
    </div>
</div>
@endsection
