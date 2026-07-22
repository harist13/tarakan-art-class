@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Buat Invoice</h1>
    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        <form action="{{ route('payments.store') }}" method="POST">
            @csrf
            @include('payments._form', ['payment' => null])
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan (Invoice otomatis)</button>
        </form>
    </div>
</div>
@endsection
