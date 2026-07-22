@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Tambah Kelas</h1>
    <a href="{{ route('classes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        @if($tutors->isEmpty())
            <div class="alert alert-warning">Belum ada tutor. Tambahkan tutor terlebih dahulu dari halaman Manajemen Kelas.</div>
        @endif
        <form action="{{ route('classes.store') }}" method="POST">
            @csrf
            @include('classes._form', ['class' => null])
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
        </form>
    </div>
</div>
@endsection
