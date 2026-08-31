@php
    $waText = 'Halo '.config('site.name').', saya ingin bertanya tentang kelas seni untuk anak saya.';
    $waUrl = 'https://wa.me/'.config('site.contact.whatsapp').'?text='.rawurlencode($waText);
@endphp

{{-- Tombol WhatsApp mengambang — tampil di semua halaman publik. --}}
<a href="{{ $waUrl }}" target="_blank" rel="noopener" class="tac-wa-float" aria-label="Chat via WhatsApp" style="color: #ffffff !important;">
    {{-- Lingkaran putih di belakang logo: memisahkan hijau terang WhatsApp
         dari hijau tua pilnya, supaya logonya tetap terbaca. --}}
    <span class="d-inline-grid rounded-circle flex-shrink-0" aria-hidden="true"
          style="width: 1.9rem; height: 1.9rem; place-items: center; background-color: #fff;">
        <img src="{{ asset('images/whatsapp.png') }}" alt="" width="30" height="30" decoding="async">
    </span>
    <span class="tac-display fw-bold d-none d-sm-inline" style="font-size: 0.9rem; color: #ffffff !important;">Chat Admin</span>
</a>
