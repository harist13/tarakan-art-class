@extends('layouts.app')

@section('content')
@php $dt = \Carbon\Carbon::createFromFormat('Y-m', $month); @endphp

<div class="d-sm-flex align-items-start justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800 fw-bold">{{ $student->name }}</h1>
        <nav aria-label="breadcrumb" class="mt-1">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('artworks.index') }}">Galeri karya</a></li>
                <li class="breadcrumb-item"><a href="{{ route('artworks.index', ['month' => $month]) }}">{{ $dt->locale('id')->translatedFormat('F Y') }}</a></li>
                <li class="breadcrumb-item active">{{ $student->name }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 mt-2 mt-sm-0">
        <a href="{{ route('artworks.create', ['student_id' => $student->id, 'month' => $month]) }}" class="btn btn-sm btn-primary shadow-sm">
            <i class="bi bi-cloud-arrow-up me-1"></i>Tambah karya
        </a>
    </div>
</div>

{{-- Jembatan ke raport bulan yang sama: karya di folder ini persis yang dilihat
     orang tua saat membuka raport tersebut dengan credential key-nya. --}}
@if($report)
    <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>
            <i class="bi bi-key-fill me-1"></i>Raport {{ $dt->locale('id')->translatedFormat('F Y') }} sudah dibuat —
            credential key <code class="fw-bold">{{ $report->credential_key }}</code>.
            Karya di folder ini ikut terlihat orang tua saat membukanya.
        </span>
        <a href="{{ route('reports.show', $report) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-journal-bookmark me-1"></i>Lihat raport</a>
    </div>
@else
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>
            <i class="bi bi-exclamation-triangle me-1"></i>Belum ada raport {{ $dt->locale('id')->translatedFormat('F Y') }} untuk {{ $student->name }},
            jadi karya ini belum bisa dibuka orang tua lewat credential key.
        </span>
        <a href="{{ route('reports.create', ['month' => $month, 'student_id' => $student->id]) }}" class="btn btn-sm btn-outline-warning text-dark"><i class="bi bi-plus-lg me-1"></i>Buat raport</a>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <span class="fw-bold"><i class="bi bi-images me-2 text-primary"></i>{{ $artworks->count() }} karya · {{ $dt->locale('id')->translatedFormat('F Y') }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @forelse($artworks as $artwork)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card border h-100 overflow-hidden">
                        <a href="{{ $artwork->photoUrl() }}" target="_blank" rel="noopener" title="Buka ukuran penuh">
                            <img src="{{ $artwork->photoUrl() }}"
                                 alt="{{ $artwork->description ?: 'Karya '.$student->name }}"
                                 class="w-100" style="height:170px; object-fit:cover; background:#f1f5f9;">
                        </a>
                        <div class="p-3">
                            <div class="small fw-semibold text-body">
                                <i class="bi bi-calendar3 text-muted me-1"></i>{{ $artwork->taken_on->format('d M Y') }}
                            </div>
                            <p class="small text-muted mb-2 mt-1">{{ $artwork->description ?: 'Tanpa deskripsi' }}</p>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-info text-white btn-edit-artwork flex-grow-1"
                                        data-action="{{ route('artworks.update', $artwork) }}"
                                        data-date="{{ $artwork->taken_on->format('Y-m-d') }}"
                                        data-description="{{ $artwork->description }}"
                                        data-photo="{{ $artwork->photoUrl() }}"
                                        title="Ubah gambar &amp; keterangan"><i class="bi bi-pencil"></i></button>
                                <form action="{{ route('artworks.destroy', $artwork) }}" method="POST" class="flex-grow-1"
                                      onsubmit="return confirm('Hapus foto karya ini? Berkasnya ikut terhapus.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger w-100" title="Hapus foto"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted py-5">
                    <i class="bi bi-images fs-1 d-block mb-3 opacity-50"></i>
                    <p class="mb-3">Folder ini masih kosong.</p>
                    <a href="{{ route('artworks.create', ['student_id' => $student->id, 'month' => $month]) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-cloud-arrow-up me-1"></i>Tambah karya
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Ubah gambar, tanggal & deskripsi satu karya. Tanggal ikut bisa diubah karena
     itu yang menentukan folder bulannya — foto yang salah tanggal tersangkut di
     bulan keliru. Gambarnya bisa diganti agar tak perlu hapus lalu unggah ulang. --}}
<div class="modal fade" id="editArtworkModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editArtworkForm" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2 text-info"></i>Ubah karya</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Gambar</label>
                        <div class="d-flex gap-3 align-items-start">
                            <img id="editArtworkPreview" alt="Gambar karya saat ini"
                                 class="rounded border flex-shrink-0"
                                 style="width:110px; height:110px; object-fit:cover; background:#f1f5f9;">
                            <div class="flex-grow-1">
                                <input type="file" name="photo" id="editArtworkPhoto" class="form-control"
                                       accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted d-block mt-1">
                                    Kosongkan bila gambarnya tidak diganti. JPG/PNG/WebP, maks 4MB.
                                    Gambar lama ikut terhapus setelah diganti.
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal karya</label>
                        <input type="date" name="taken_on" class="form-control" max="{{ now()->toDateString() }}" required>
                        <small class="text-muted">Mengubah bulannya akan memindahkan foto ini ke folder bulan lain.</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Deskripsi <span class="text-muted">(opsional)</span></label>
                        <input type="text" name="description" class="form-control" maxlength="255" placeholder="mis. Melukis tema laut">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('editArtworkModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('editArtworkForm');
    const fileInput = document.getElementById('editArtworkPhoto');
    const preview = document.getElementById('editArtworkPreview');

    document.querySelectorAll('.btn-edit-artwork').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.dataset.action;
            form.querySelector('[name="taken_on"]').value = btn.dataset.date;
            form.querySelector('[name="description"]').value = btn.dataset.description || '';
            // Modalnya dipakai bergantian untuk semua kartu, jadi pilihan berkas
            // dari kartu sebelumnya harus dibuang — kalau tidak, gambar kartu ini
            // ikut tertimpa berkas yang tadi dipilih untuk kartu lain.
            fileInput.value = '';
            preview.src = btn.dataset.photo;
            modal.show();
        });
    });

    // Pratinjau gambar pengganti sebelum disimpan.
    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        preview.onload = function () { URL.revokeObjectURL(url); };
        preview.src = url;
    });
});
</script>
@endpush
