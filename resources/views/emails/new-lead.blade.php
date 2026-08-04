@php
    $waDigits = preg_replace('/\D/', '', $lead->parent_phone);
    // Nomor lokal (08xx) diubah ke format internasional untuk tautan wa.me.
    $waLink = str_starts_with($waDigits, '0') ? '62'.substr($waDigits, 1) : $waDigits;
@endphp
<x-mail::message>
# Calon murid baru

Ada calon murid yang mengisi form kontak di website {{ config('site.name') }}.

**Anak:** {{ $lead->child_name }}@if($lead->child_age) ({{ $lead->child_age }} tahun)@endif
@if($lead->date_of_birth)
**Tanggal lahir:** {{ $lead->date_of_birth->format('d M Y') }}
@endif

**Orang tua:** {{ $lead->parent_name }}
**WhatsApp:** {{ $lead->parent_phone }}
@if($lead->parent_email)
**Email:** {{ $lead->parent_email }}
@endif
@if($lead->address)
**Alamat:** {{ $lead->address }}
@endif
**Tipe kelas:** {{ $lead->classTypeName() ?? 'Belum ditentukan' }}
**Kelas diminati:** {{ $lead->programName() ?? 'Belum ditentukan' }}
**Masuk:** {{ $lead->created_at->format('d M Y H:i') }}

@if($lead->message)
**Pesan:**

> {{ $lead->message }}
@endif

<x-mail::button :url="'https://wa.me/'.$waLink">
Balas via WhatsApp
</x-mail::button>

Pendaftaran final tetap diproses lewat modul Murid & Wali di sistem admin.

Salam,<br>
{{ config('site.name') }}
</x-mail::message>
