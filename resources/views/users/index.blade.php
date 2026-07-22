@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Manajemen User & Admin</h1>
    <a href="{{ route('users.create') }}" class="btn btn-sm btn-primary shadow-sm">
        <i class="bi bi-plus-lg"></i> Tambah User
    </a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Akun Pengguna</span>
        <form method="GET" data-live class="d-flex" style="max-width:300px;">
            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm me-2" placeholder="Cari nama / email...">
            <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Nama Lengkap</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="fw-bold">{{ $user->full_name }}</td>
                            <td>{{ $user->username }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="badge bg-{{ $user->role === 'super_admin' ? 'primary' : 'info' }}">
                                    {{ $user->role === 'super_admin' ? 'Super Admin' : 'Admin' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $user->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                @if($user->id !== auth()->id())
                                <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus akun ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Tidak ada data pengguna.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</div>
@endsection
