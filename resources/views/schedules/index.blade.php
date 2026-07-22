@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Jadwal & Replacement Class</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('schedules.calendar') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar3"></i> Lihat Kalender</a>
        <a href="{{ route('schedules.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Ajukan Replacement</a>
    </div>
</div>

{{-- Panel kelola ketersediaan slot: tutup slot yang tak bisa diisi agar tidak muncul saat cari kelas pengganti. --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-bold"><i class="bi bi-toggles me-2 text-primary"></i>Ketersediaan Slot Kelas</span>
        <small class="text-muted">Tutup slot yang penuh/tidak layak agar tak muncul saat mencari kelas pengganti.</small>
    </div>
    <div class="card-body">
        <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Kelas</th><th>Jadwal</th><th>Terisi</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($slots as $slot)
                        @php $av = $slot->availability(); @endphp
                        <tr>
                            <td class="fw-bold">{{ $slot->class_name }}<br><small class="text-muted">{{ $slot->class_code }} · {{ ucfirst($slot->class_category) }}</small></td>
                            <td>{{ $slot->schedule_date->format('d M Y') }}<br><small class="text-muted">{{ \Illuminate\Support\Str::of($slot->schedule_time)->substr(0,5) }}</small></td>
                            <td>{{ $slot->enrolledCount() }} / {{ $slot->capacity }}</td>
                            <td><span class="badge bg-{{ $av['color'] }}">{{ $av['text'] }}</span></td>
                            <td class="text-end">
                                <form action="{{ route('classes.toggle-status', $slot) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    @if($slot->isClosed())
                                        <button class="btn btn-sm btn-outline-success" title="Buka slot"><i class="bi bi-unlock me-1"></i>Buka</button>
                                    @else
                                        <button class="btn btn-sm btn-outline-secondary" title="Tutup slot"><i class="bi bi-lock me-1"></i>Tutup</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Belum ada kelas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Daftar Request Replacement Class</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:200px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari murid / kelas...">
            </div>
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua Status</option>
                <option value="pending" @selected($status === 'pending')>Pending</option>
                <option value="approved" @selected($status === 'approved')>Approved</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
            </select>
            @if($search !== '' || $status !== '')
                <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Murid</th><th>Kelas Asal</th><th>Tanggal Baru</th><th>Jam</th><th>Alasan</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td class="fw-bold">{{ $req->student->name ?? '-' }}</td>
                            <td>{{ $req->classRoom->class_name ?? '-' }}</td>
                            <td>{{ $req->replacement_date->format('d M Y') }}</td>
                            <td>{{ \Illuminate\Support\Str::of($req->replacement_time)->substr(0,5) }}</td>
                            <td class="small">{{ $req->reason ?: '-' }}</td>
                            <td>
                                @php $colors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger']; @endphp
                                <span class="badge bg-{{ $colors[$req->request_status] }}">{{ ucfirst($req->request_status) }}</span>
                                @if($req->approver)<br><small class="text-muted">oleh {{ $req->approver->full_name }}</small>@endif
                            </td>
                            <td class="text-end">
                                @if($req->request_status === 'pending' && auth()->user()->isSuperAdmin())
                                    <form action="{{ route('schedules.status', $req) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="request_status" value="approved">
                                        <button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                    <form action="{{ route('schedules.status', $req) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="request_status" value="rejected">
                                        <button class="btn btn-sm btn-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                @endif
                                <a href="{{ route('schedules.edit', $req) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('schedules.destroy', $req) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus request ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Belum ada request replacement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    </div>
</div>
@endsection
