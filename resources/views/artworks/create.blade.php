@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Tambah Foto Karya</h1>
    <a href="{{ $month ? route('artworks.index', ['month' => $month]) : route('artworks.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info small mb-4">
            <i class="bi bi-folder me-1"></i>Foto masuk ke folder bulan sesuai <strong>tanggal karya</strong> yang diisi di bawah — tidak ada folder yang perlu dibuat manual.
            Karya bulan yang sama dengan periode raport murid ini akan ikut terlihat orang tua lewat credential key.
        </div>

        <form action="{{ route('artworks.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label class="form-label">Murid</label>
                    <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                        <option value="">— Pilih Murid —</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" @selected(old('student_id', $studentId) == $student->id)>
                                {{ $student->name }} ({{ $student->student_id }})
                            </option>
                        @endforeach
                    </select>
                    @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($students->isEmpty())
                        <small class="text-danger d-block mt-1">Belum ada murid aktif — tambahkan lewat <a href="{{ route('students.index') }}">Data Murid &amp; Wali</a>.</small>
                    @endif
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tanggal Karya</label>
                    <input type="date" name="taken_on" class="form-control @error('taken_on') is-invalid @enderror"
                           value="{{ old('taken_on', $defaultDate) }}" max="{{ now()->toDateString() }}" required>
                    @error('taken_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted d-block mt-1">Menentukan folder bulannya.</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Deskripsi <span class="text-muted">(opsional)</span></label>
                    <input type="text" name="description" class="form-control @error('description') is-invalid @enderror"
                           value="{{ old('description') }}" maxlength="255" placeholder="mis. Melukis tema laut">
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted d-block mt-1">Berlaku untuk semua foto di unggahan ini; bisa diubah per foto nanti.</small>
                </div>

                <div class="col-12 mb-3">
                    <label class="form-label">Foto Karya</label>
                    <input type="file" name="photos[]" id="photoInput"
                           class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
                           accept="image/jpeg,image/png,image/webp" multiple required>
                    @error('photos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('photos.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted d-block mt-1">JPG/PNG/WebP, maks 4MB per foto, sampai 12 foto sekali unggah.</small>

                    <div id="photoPreview" class="row g-2 mt-2"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-arrow-up me-1"></i> Unggah</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Pratinjau sebelum unggah: memilih 12 foto tanpa melihatnya mudah keliru murid.
document.getElementById('photoInput')?.addEventListener('change', function () {
    const wrap = document.getElementById('photoPreview');
    wrap.innerHTML = '';
    Array.from(this.files).forEach(function (file) {
        const col = document.createElement('div');
        col.className = 'col-4 col-md-2';
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.className = 'w-100 rounded border';
        img.style.cssText = 'height:90px; object-fit:cover;';
        // Lepas object URL setelah gambar termuat agar tidak menumpuk di memori.
        img.onload = function () { URL.revokeObjectURL(img.src); };
        col.appendChild(img);
        wrap.appendChild(col);
    });
});
</script>
@endpush
