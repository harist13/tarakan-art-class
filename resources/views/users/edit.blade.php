@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Edit User</h1>
    <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf @method('PUT')
            @include('users._form', ['user' => $user])
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Perbarui</button>
        </form>
    </div>
</div>
@endsection
