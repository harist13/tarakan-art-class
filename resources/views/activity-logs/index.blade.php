@extends('layouts.app')

@section('title', 'Log Aktivitas')

@section('content')
@php
    $actionMeta = [
        'created' => ['label' => 'Dibuat',    'badge' => 'success', 'icon' => 'bi-plus-circle'],
        'updated' => ['label' => 'Diperbarui', 'badge' => 'info',    'icon' => 'bi-pencil-square'],
        'deleted' => ['label' => 'Dihapus',   'badge' => 'danger',  'icon' => 'bi-trash'],
        'sent'    => ['label' => 'Dikirim',   'badge' => 'primary', 'icon' => 'bi-send'],
    ];
    $subjectLabels = [
        'Student'            => 'Murid',
        'ClassRoom'          => 'Kelas',
        'Tutor'              => 'Tutor',
        'ReplacementRequest' => 'Replacement',
        'Attendance'         => 'Absensi',
        'Payment'            => 'Pembayaran',
        'Transaction'        => 'Keuangan',
        'StudentReport'      => 'Raport',
        'InventoryItem'      => 'Inventaris',
        'StockMovement'      => 'Stok',
        'User'               => 'User',
    ];
@endphp

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Log Aktivitas</h1>
        <p class="text-muted small mb-0">Jejak aktivitas seluruh pengguna sistem (F15).</p>
    </div>
    <span class="badge bg-primary align-self-center"><i class="bi bi-clock-history me-1"></i>{{ $logs->total() }} aktivitas</span>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Riwayat Aktivitas</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <select name="user" class="form-select form-select-sm" style="width:170px;">
                <option value="">Semua User</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((string) $userId === (string) $u->id)>{{ $u->full_name }}</option>
                @endforeach
            </select>
            <select name="action" class="form-select form-select-sm" style="width:170px;">
                <option value="">Semua Aksi</option>
                @foreach($actionMeta as $key => $meta)
                    <option value="{{ $key }}" @selected($action === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <div class="input-group input-group-sm" style="width:170px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari deskripsi...">
            </div>
            @if($userId !== '' || $action !== '' || $search !== '')
                <a href="{{ route('activity-logs.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width:170px;">Waktu</th>
                        <th>User</th>
                        <th style="width:120px;">Aksi</th>
                        <th style="width:130px;">Objek</th>
                        <th>Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $meta = $actionMeta[$log->action] ?? ['label' => ucfirst($log->action), 'badge' => 'secondary', 'icon' => 'bi-dot'];
                            $subjectBase = $log->subject_type ? class_basename($log->subject_type) : null;
                            $subjectLabel = $subjectBase ? ($subjectLabels[$subjectBase] ?? $subjectBase) : null;
                        @endphp
                        <tr>
                            <td class="small">
                                <div class="fw-semibold">{{ $log->logged_at?->format('d M Y') }}</div>
                                <span class="text-muted">{{ $log->logged_at?->format('H:i') }} · {{ $log->logged_at?->diffForHumans() }}</span>
                            </td>
                            <td>
                                @if($log->user)
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($log->user->full_name) }}&background=0EA5E9&color=fff&size=28" width="28" height="28" class="rounded-circle">
                                        <span class="fw-semibold small">{{ $log->user->full_name }}</span>
                                    </div>
                                @else
                                    <span class="text-muted small">— (user dihapus)</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $meta['badge'] }}"><i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}</span>
                            </td>
                            <td>
                                @if($subjectLabel)
                                    <span class="badge bg-light text-dark border">{{ $subjectLabel }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="small">{{ $log->description ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Belum ada aktivitas yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
</div>
@endsection
