@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Jadwalkan Holiday Class</h1>
    <a href="{{ route('holiday-classes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="card">
    <div class="card-body">
        <form action="{{ route('holiday-classes.store') }}" method="POST">
            @csrf
            @include('holiday-classes._form', ['class' => null])
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
        </form>
    </div>
</div>
@endsection
