@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Atur Replacement Class</h1>
    <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i> Replacement langsung berlaku begitu disimpan — murid pindah ke sesi pengganti di absensi &amp; kalender tanpa menunggu persetujuan.</div>
        <form action="{{ route('schedules.store') }}" method="POST">
            @csrf
            @include('schedules._form', ['request' => null])
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Simpan replacement</button>
        </form>
    </div>
</div>
@endsection
