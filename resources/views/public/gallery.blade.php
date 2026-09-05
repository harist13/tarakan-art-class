@extends('layouts.public')

@section('title', 'Galeri karya')
@section('description', 'Kumpulan karya murid Tarakan Art Class dan dokumentasi kegiatan kelas, Holiday Class, serta pameran karya anak di Tarakan.')

@section('content')

<x-site.section tone="paper-2" :paint="true">
    <x-site.heading
        level="h1"
        eyebrow="Galeri"
        title="Setiap coretan punya ceritanya"
        subtitle="Karya murid dari kelas mingguan, Holiday Class, dan dokumentasi kegiatan bersama." />

    @if($categories->isNotEmpty())
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
            <a href="{{ route('public.gallery') }}"
               class="tac-btn tac-btn-sm {{ $active ? 'bg-white tac-text-ink border' : '' }}"
               @if(! $active) aria-current="page" @endif>
                Semua
            </a>
            @foreach($categories as $slug => $label)
                <a href="{{ route('public.gallery', ['kategori' => $slug]) }}"
                   class="tac-btn tac-btn-sm {{ $active === $slug ? '' : 'bg-white tac-text-ink border' }}"
                   @if($active === $slug) aria-current="page" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    @endif
</x-site.section>

<x-site.section tone="paper">
    @if($items->isNotEmpty())
        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-4">
            @foreach($items as $item)
                <div class="col">
                    <figure class="tac-card tac-card-hover overflow-hidden h-100 mb-0">
                        <img src="{{ $item['url'] }}"
                             alt="Karya murid Tarakan Art Class"
                             loading="lazy" decoding="async" class="tac-thumb">
                    </figure>
                </div>
            @endforeach
        </div>

        @if($items->hasPages())
            <div class="d-flex justify-content-center mt-5">
                {{ $items->links() }}
            </div>
        @endif
    @else
        {{-- Belum ada karya yang diunggah admin (Galeri karya) maupun foto statis
             di config/site.php → arahkan ke Instagram. --}}
        <div class="mx-auto text-center" style="max-width: 34rem;">
            <span class="tac-dashed-box tac-thumb-placeholder mx-auto" style="width: 5rem; height: 5rem;" aria-hidden="true">🖼️</span>
            <h2 class="fs-3 mt-4 mb-3">Galeri sedang kami susun</h2>
            <p class="small lh-lg tac-muted mb-0">
                Dokumentasi karya dan kegiatan terbaru kami unggah lebih dulu di Instagram.
                Mampir ke sana untuk melihat apa yang sedang dikerjakan murid-murid kami minggu ini.
            </p>
            <div class="mt-4">
                <x-site.btn :href="'https://instagram.com/'.config('site.contact.instagram')" target="_blank" rel="noopener">
                    Buka &#64;{{ config('site.contact.instagram') }}
                </x-site.btn>
            </div>
        </div>
    @endif
</x-site.section>

{{-- ─── Instagram ──────────────────────────────────────────────── --}}
<x-site.section tone="paper-2">
    <div class="tac-card p-4 p-sm-5">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6">
                <span class="tac-icon tac-bg-grape tac-text-paper" aria-hidden="true">📸</span>
                <h2 class="fs-3 mt-4 mb-3">Ikuti keseharian kelas kami</h2>
                <p class="small lh-lg tac-muted mb-0">
                    Setiap minggu kami membagikan proses berkarya, tips menggambar di rumah, dan
                    pengumuman Holiday Class lewat Instagram.
                </p>
                <div class="mt-4">
                    <x-site.btn :href="'https://instagram.com/'.config('site.contact.instagram')" variant="coral" target="_blank" rel="noopener">
                        &#64;{{ config('site.contact.instagram') }}
                    </x-site.btn>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="row row-cols-3 g-3" aria-hidden="true">
                    @foreach(['tac-bg-coral-soft','tac-bg-sun-soft','tac-bg-sky-soft','tac-bg-leaf-soft','tac-bg-grape-soft','tac-bg-sun-soft','tac-bg-sky-soft','tac-bg-coral-soft','tac-bg-leaf-soft'] as $tone)
                        <div class="col">
                            <div class="{{ $tone }} tac-thumb tac-thumb-placeholder"
                                 style="border-radius: 1.1rem; font-size: 1.25rem;">🎨</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-site.section>

<x-site.cta
    title="Ingin karya anak Anda ada di sini?"
    subtitle="Daftarkan anak Anda ke kelas berikutnya dan mulai kumpulkan karyanya bersama kami." />

@endsection
