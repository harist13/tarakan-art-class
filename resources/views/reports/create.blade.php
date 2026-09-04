@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Buat raport siswa</h1>
    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        <div class="alert alert-info small"><i class="bi bi-key me-1"></i> Credential key untuk akses orang tua akan di-generate otomatis setelah raport disimpan.</div>
        <form action="{{ route('reports.store') }}" method="POST">
            @csrf
            @include('reports._form', ['report' => null])
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
        </form>
    </div>
</div>
@endsection
