@php
    // datetime-local butuh format Y-m-dTH:i. Default untuk sesi baru: akhir pekan
    // terdekat pukul 09.00, jam yang paling sering dipakai sesi liburan.
    $defaultSchedule = now()->next(\Carbon\CarbonInterface::SATURDAY)->setTime(9, 0)->format('Y-m-d\TH:i');
    $schedule = old('schedule', isset($class) ? $class->schedule->format('Y-m-d\TH:i') : $defaultSchedule);
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nama sesi</label>
        <input type="text" name="class_name" class="form-control @error('class_name') is-invalid @enderror"
               value="{{ old('class_name', $class->class_name ?? '') }}"
               list="holidayClassNames" placeholder="mis. Melukis Tote Bag" required>
        <datalist id="holidayClassNames">
            <option value="Melukis Tote Bag"><option value="Clay & Keramik Mini"><option value="Mural Mini"><option value="Coding Camp">
        </datalist>
        <div class="form-text">Tema sesi ini — nama inilah yang muncul di pengumuman website.</div>
        @error('class_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Jadwal (tanggal &amp; jam mulai)</label>
        <input type="datetime-local" name="schedule" class="form-control @error('schedule') is-invalid @enderror"
               value="{{ $schedule }}" required>
        <div class="form-text">Sesi yang tanggalnya sudah lewat otomatis berhenti tampil di website.</div>
        @error('schedule') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Kapasitas (anak)</label>
        <input type="number" min="1" max="500" name="capacity" class="form-control @error('capacity') is-invalid @enderror"
               value="{{ old('capacity', $class->capacity ?? 12) }}" required>
        @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Biaya per peserta (Rp)</label>
        <input type="number" step="1000" min="0" name="price" class="form-control @error('price') is-invalid @enderror"
               value="{{ old('price', isset($class) ? (int) $class->price : 150000) }}" required>
        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="alert alert-light border small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    Sesi ini tidak otomatis tercatat di Laporan keuangan — biaya di atas baru berupa harga
    yang diumumkan. Pemasukan dicatat lewat menu <strong>Pembayaran</strong> saat peserta membayar.
</div>
