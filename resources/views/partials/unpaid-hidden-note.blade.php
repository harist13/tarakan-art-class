{{-- Catatan jumlah data akademik yang disembunyikan karena murid belum berstatus lunas.
     Pakai: @include('partials.unpaid-hidden-note', ['hiddenCount' => $hiddenCount, 'label' => 'data absensi']) --}}
@if(($hiddenCount ?? 0) > 0)
    <div class="alert alert-light border small d-flex align-items-start gap-2 py-2">
        <i class="bi bi-eye-slash text-warning mt-1"></i>
        <div>
            <strong>{{ $hiddenCount }} {{ $label }}</strong> disembunyikan karena muridnya belum berstatus lunas —
            entah masih punya invoice belum lunas, atau <strong>belum pernah dibuatkan invoice sama sekali</strong>.
            Penyebab per murid bisa dilihat lewat badge di <a href="{{ route('students.index') }}" class="alert-link">Data Murid &amp; Wali</a>;
            buat atau lunasi invoice-nya di <a href="{{ route('payments.index') }}" class="alert-link">menu Pembayaran</a>, lalu data muncul kembali otomatis.
        </div>
    </div>
@endif
