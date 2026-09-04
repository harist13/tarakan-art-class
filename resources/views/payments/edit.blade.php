@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Edit pembayaran — {{ $payment->invoice_number }}</h1>
    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        <form action="{{ route('payments.update', $payment) }}" method="POST">
            @csrf @method('PUT')
            @include('payments._form', ['payment' => $payment])
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Perbarui</button>
        </form>
    </div>
</div>
@endsection
