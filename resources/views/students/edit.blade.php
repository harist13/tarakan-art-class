@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Edit Murid — {{ $student->student_id }}</h1>
        <p class="text-muted small mb-0">Perbarui data identitas murid atau status program kelas.</p>
    </div>
    <a href="{{ route('students.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form action="{{ route('students.update', $student) }}" method="POST">
            @csrf @method('PUT')
            @include('students._form', ['student' => $student])
            <div class="d-flex align-items-center gap-2 pt-3 border-top mt-2">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                </button>
                <a href="{{ route('students.index') }}" class="btn btn-light px-3">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
