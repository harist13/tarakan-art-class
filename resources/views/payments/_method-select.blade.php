{{--
    Pilihan Metode / channel — dipakai form Buat invoice maupun Tagihan bulanan.

    Daftar opsinya diambil dari Payment::METHODS, satu-satunya tempat daftar
    metode ditulis. Dua halaman itu memang berbeda peran, tapi kosakata
    metodenya harus sama persis; disalin dua kali, cepat atau lambat yang satu
    menawarkan pilihan yang ditolak validasi milik yang lain.

    Wajib dikirim:
      $selected — nilai yang sedang berlaku (boleh nilai warisan seperti
                  'ewallet'; dinormalkan di sini supaya invoice lama tidak
                  diam-diam berubah jadi Cash saat disimpan ulang).
--}}
@php $current = \App\Models\Payment::normalizeMethod($selected ?: null); @endphp
<select name="payment_method" class="form-select" required>
    @foreach(\App\Models\Payment::methodFormOptions() as $value => $label)
        <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
    @endforeach
</select>
