@extends('layouts.public')

@section('title', 'Program & kelas')
@section('description', 'Preschool Art, Coloring Class, Drawing Class, dan Holiday Class di Tarakan. Lihat rentang usia, durasi, kapasitas, dan biaya tiap kelas.')

@section('content')

<x-site.section tone="paper-2" :paint="true">
    <x-site.heading
        level="h1"
        eyebrow="Program & kelas"
        title="Pilih kelas yang pas untuk anak"
        subtitle="Setiap kelas dikelompokkan menurut usia dan kemampuan, jadi anak belajar bersama teman yang setara. Belum yakin yang mana? Sapa kami dan kami bantu memilihkan." />

    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
        @foreach($programs as $program)
            <a href="#{{ $program['slug'] }}" class="tac-btn tac-btn-sm bg-white tac-text-ink border">
                {{ $program['name'] }}
            </a>
        @endforeach
    </div>
</x-site.section>

<x-site.section tone="paper">
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 justify-content-center">
        @foreach($programs as $program)
            <div class="col">
                <x-site.program-card :program="$program" :detailed="true" :id="$program['slug']" class="tac-scroll-target" />
            </div>
        @endforeach
    </div>

    <p class="tac-dashed-box tac-bg-paper-2 mx-auto text-center small tac-muted px-4 py-3 mt-5 mb-0" style="max-width: 44rem;">
        Biaya sudah termasuk seluruh alat dan bahan. Jadwal di atas adalah jadwal umum —
        jadwal pasti per angkatan dikonfirmasi admin saat pendaftaran.
        <a href="{{ route('public.schedule') }}" class="fw-semibold tac-text-coral">Lihat jadwal terbaru</a>.
    </p>
</x-site.section>

<x-site.cta
    title="Sudah menemukan kelas yang cocok?"
    subtitle="Kirim data anak lewat form pendaftaran, dan admin kami akan menghubungi Anda untuk mengatur jadwal." />

@endsection
