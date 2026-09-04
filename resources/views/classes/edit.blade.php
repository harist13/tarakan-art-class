@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Edit Kelas — {{ $class->class_code }}</h1>
    <a href="{{ route('classes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        <form action="{{ route('classes.update', $class) }}" method="POST">
            @csrf @method('PUT')
            @include('classes._form', ['class' => $class])
            {{-- Diberi jarak & garis pemisah: tombolnya menempel pada kotak harga
                 di atasnya, sehingga terbaca seolah bagian dari kotak itu. --}}
            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endsection
