@extends('layouts.public')

@section('title', 'Program & Kelas')
@section('description', 'Preschool Art, Coloring Class, Drawing Class, dan Holiday Class untuk anak usia 3–12 tahun di Tarakan. Lihat rentang usia, durasi, kapasitas, dan biaya tiap kelas.')

@section('content')

<x-site.section tone="paper-2" :paint="true">
    <x-site.heading
        level="h1"
        eyebrow="Program & Kelas"
        title="Pilih kelas yang pas untuk anak"
        subtitle="Setiap kelas dikelompokkan menurut usia dan kemampuan, jadi anak belajar bersama teman yang setara. Belum yakin yang mana? Sapa kami dan kami bantu memilihkan." />

    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
        @foreach($programs as $program)
            <a href="#{{ $program['slug'] }}" class="tac-btn tac-btn-sm bg-white tac-text-ink">
                {{ $program['name'] }}
            </a>
        @endforeach
    </div>
</x-site.section>

<x-site.section tone="paper">
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
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

{{-- FAQ --}}
<x-site.section tone="paper-2">
    <x-site.heading
        eyebrow="FAQ"
        title="Pertanyaan yang sering ditanyakan"
        subtitle="Belum terjawab? Chat admin lewat tombol WhatsApp di pojok layar." />

    <div class="mx-auto mt-5 d-grid gap-3" style="max-width: 44rem;">
        @foreach($faq as $item)
            <details class="tac-card tac-faq px-4 py-3">
                <summary class="d-flex justify-content-between align-items-center gap-3 tac-display fw-bold">
                    <span>{{ $item['q'] }}</span>
                    <span class="tac-faq-toggle" aria-hidden="true">+</span>
                </summary>
                <p class="tac-dashed-top small lh-lg tac-muted mt-3 pt-3 mb-0">{{ $item['a'] }}</p>
            </details>
        @endforeach
    </div>
</x-site.section>

<x-site.cta
    title="Sudah menemukan kelas yang cocok?"
    subtitle="Kirim data anak lewat form pendaftaran, dan admin kami akan menghubungi Anda untuk mengatur jadwal." />

@endsection
