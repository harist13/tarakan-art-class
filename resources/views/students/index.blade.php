@extends('layouts.app')

@push('styles')
<style>
    /* Tabel murid: 7 kolom cukup padat, jadi padding dirapatkan sedikit
       dan kolom aksi dibuat menempel di kanan saat tabel di-scroll. */
    .students-table thead th,
    .students-table tbody td { padding: 0.85rem 1rem; }
    .students-table th { white-space: nowrap; }
    .students-table .cell-main { font-weight: 600; line-height: 1.25; }
    .students-table .cell-sub { font-size: 0.8rem; color: var(--text-muted); line-height: 1.25; }
    .students-table .col-actions { position: sticky; right: 0; background-color: var(--surface); }
    .students-table thead .col-actions { background-color: var(--surface-2); }
    .students-table .col-actions::before {
        content: ''; position: absolute; top: 0; bottom: 0; left: 0;
        border-left: 1px solid var(--border);
    }
    .students-table tbody tr:hover td.col-actions { background-color: var(--surface-2); }
</style>
@endpush

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Data murid & wali</h1>
    <div class="d-flex gap-2">
        @include('partials.export-buttons', ['route' => 'export.students'])
        <a href="{{ route('students.create') }}" class="btn btn-sm btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Tambah murid
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Daftar murid</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            {{-- Tombol saringnya berupa <a>, bukan checkbox: data-live hanya
                 mengawasi teks, select, & tanggal, dan menambah checkbox ke
                 pengawasan itu akan mengubah perilaku 11 halaman lain. Nilainya
                 dititipkan sebagai hidden input supaya tidak hilang saat filter
                 lain berubah dan form ini terkirim ulang. --}}
            @if($unbilled)
                <input type="hidden" name="unbilled" value="1">
            @endif
            <div class="input-group input-group-sm" style="width:180px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari nama / ID...">
            </div>
            <select name="class_id" class="form-select form-select-sm" style="width:160px;">
                <option value="">Semua kelas</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected($classId == $class->id)>{{ $class->class_category }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
            </select>
            {{-- Saringan "belum ditagih". Angkanya dihitung dari seluruh murid,
                 bukan dari halaman yang sedang tampil — badge yang tersebar di
                 beberapa halaman paginasi tidak bisa dijumlahkan dengan mata. --}}
            <a href="{{ route('students.index', array_merge(
                    request()->except(['page', 'unbilled']),
                    $unbilled ? [] : ['unbilled' => 1]
               )) }}"
               class="btn btn-sm {{ $unbilled ? 'btn-info text-white shadow-sm' : ($unbilledCount > 0 ? 'btn-outline-info' : 'btn-outline-secondary') }} text-nowrap rounded-pill px-3 py-1 fw-semibold"
               title="{{ $unbilled
                   ? 'Tampilkan kembali semua murid.'
                   : 'Saring murid yang belum punya invoice untuk '.\App\Models\Payment::labelForPeriod(\App\Models\Payment::periodFor()).'.' }}">
                <i class="bi bi-receipt me-1"></i>Belum ditagih
                <span class="badge rounded-pill ms-1 fw-bold" style="background-color: rgba(245, 136, 12, 1); color: #FFFFFF !important; font-size: 0.75rem; padding: 0.2rem 0.55rem;">{{ $unbilledCount }}</span>
            </a>
            @if($search !== '' || $classId || $status !== '' || $unbilled)
                <a href="{{ route('students.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        {{-- Saringan ini menyisakan satu pertanyaan: lalu apa? Jawabannya
             ditaruh di sini sebagai tautan langsung, bukan dibiarkan dicari
             sendiri di menu Pembayaran. --}}
        @if($unbilled)
            <div class="alert alert-info small d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span>
                    <i class="bi bi-receipt me-1"></i>
                    Menampilkan murid yang belum punya invoice untuk
                    <strong>{{ \App\Models\Payment::labelForPeriod(\App\Models\Payment::periodFor()) }}</strong>.
                    Murid nonaktif, yang ditangguhkan, dan yang belum punya kelas berbiaya tidak dihitung.
                </span>
                <a href="{{ route('payments.create') }}" class="btn btn-sm btn-info text-white text-nowrap">
                    <i class="bi bi-calendar-plus me-1"></i> Terbitkan tagihan
                </a>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle students-table">
                <thead>
                    <tr>
                        <th>Murid</th>
                        <th>Usia</th>
                        <th>Kelas</th>
                        <th>Wali</th>
                        <th>Bergabung</th>
                        <th>Status</th>
                        <th class="text-end col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td>
                                <div class="cell-main">{{ $student->name }}</div>
                                <div class="cell-sub">{{ $student->student_id }}</div>
                            </td>
                            <td class="text-nowrap">
                                {{ $student->age !== null ? $student->age.' th' : '-' }}
                                @if($student->has_manual_age)
                                    <i class="bi bi-pencil-fill text-muted small" title="Usia diisi manual (hitungan dari tanggal lahir: {{ $student->calculated_age }} th)"></i>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($student->classes as $class)
                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-1 fw-semibold">{{ $class->class_category }}</span>
                                    @empty
                                        <span class="text-muted small">Belum ada kelas</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="cell-main">{{ $student->parent_name }}</div>
                                <div class="cell-sub">{{ $student->phone_number }}</div>
                            </td>
                            <td class="text-nowrap">{{ optional($student->join_date)->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                <div class="d-flex flex-column align-items-start gap-1">
                                    @if($student->status === 'active')
                                        <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: #15803D;">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: #475569;">
                                            {{ ucfirst($student->status) }}
                                        </span>
                                    @endif
                                    {{-- Penangguhan sistem: murid keluar dari daftar kelas ke depan, datanya tetap utuh. --}}
                                    @if($student->isSuspended())
                                        <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: #DC2626;"
                                              title="{{ $student->suspended_reason }} — tidak masuk daftar kelas berikutnya sampai tunggakan lunas.">
                                            <i class="bi bi-pause-circle-fill me-1"></i>Ditangguhkan
                                        </span>
                                    @endif
                                    {{-- Penanda tagihan. Absensi & raport tetap jalan; yang ditahan hanya
                                         kelas pengganti, pindah kelas, dan akses raport orang tua.
                                         Label, warna, & penjelasannya ditentukan Student::paymentBadge()
                                         — tiga keadaan berbeda tidak muat lagi dalam ternary di sini. --}}
                                    @if($badge = $student->paymentBadge())
                                        <span class="badge {{ $badge['class'] }}" style="{{ $badge['style'] ?? '' }}" title="{{ $badge['title'] }}">
                                            <i class="bi {{ $badge['icon'] }} me-1"></i>{{ $badge['label'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end text-nowrap col-actions">
                                <a href="{{ route('students.edit', $student) }}" class="btn btn-sm btn-info text-white" title="Edit murid"><i class="bi bi-pencil"></i></a>
                                @if(auth()->user()->isSuperAdmin())
                                <form action="{{ route('students.destroy', $student) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus permanen murid ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="Hapus murid"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        {{-- Kosong saat saringan "belum ditagih" aktif berarti kabar
                             baik, bukan data yang hilang — jangan disamakan. --}}
                        <tr><td colspan="7" class="text-center text-muted">
                            @if($unbilled)
                                <i class="bi bi-check-circle text-success me-1"></i>
                                Semua murid sudah punya invoice untuk {{ \App\Models\Payment::labelForPeriod(\App\Models\Payment::periodFor()) }}.
                            @else
                                Tidak ada data murid.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
</div>
@endsection
