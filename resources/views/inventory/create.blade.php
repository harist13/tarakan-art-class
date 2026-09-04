@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Tambah barang</h1>
    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        <form action="{{ route('inventory.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Nama barang</label>
                    <input type="text" name="item_name" class="form-control" value="{{ old('item_name') }}" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Stok awal</label>
                    <input type="number" min="0" name="remaining_stock" class="form-control" value="{{ old('remaining_stock', 0) }}" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Harga beli (Rp)</label>
                    <input type="number" step="500" min="0" name="purchase_price" class="form-control" value="{{ old('purchase_price') }}" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Harga jual (Rp)</label>
                    <input type="number" step="500" min="0" name="selling_price" class="form-control" value="{{ old('selling_price') }}" required></div>
            </div>
            <div class="alert alert-info py-2 small d-flex align-items-start gap-2">
                <i class="bi bi-info-circle-fill mt-1"></i>
                <span>
                    Belanja barang ini otomatis tercatat sebagai <strong>pengeluaran</strong> di menu Laporan keuangan
                    sebesar <strong>stok awal &times; harga beli</strong>. Isi stok awal 0 atau harga beli 0 bila barang
                    tidak dibeli (mis. hibah) agar tidak ikut dicatat.
                </span>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan (Kode otomatis)</button>
        </form>
    </div>
</div>
@endsection
