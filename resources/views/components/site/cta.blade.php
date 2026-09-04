@props([
    'title' => 'Siap mulai kelas pertama?',
    'subtitle' => 'Isi form pendaftaran atau sapa admin kami lewat WhatsApp. Kami balas di jam operasional.',
])

@php
    $waText = 'Halo '.config('site.name').', saya ingin menanyakan kelas untuk anak saya.';
    $waUrl = 'https://wa.me/'.config('site.contact.whatsapp').'?text='.rawurlencode($waText);
@endphp

<x-site.section tone="ink">
    <div class="position-relative overflow-hidden text-center px-3 px-sm-5 py-5"
         style="border: 1px solid rgba(255, 251, 243, 0.14); border-radius: 2rem; background-color: rgba(255, 251, 243, 0.06);">
        <span class="tac-deco tac-float" style="top: -0.5rem; left: -0.5rem;" aria-hidden="true">🖍️</span>
        <span class="tac-deco tac-float-slow" style="bottom: -1rem; right: -0.5rem;" aria-hidden="true">🎨</span>

        <h2 class="fs-2 lh-sm tac-text-paper mb-0">{{ $title }}</h2>
        <p class="mx-auto mt-3 mb-0 tac-muted-invert" style="max-width: 34rem;">{{ $subtitle }}</p>

        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mt-4">
            <x-site.btn :href="route('public.contact')" variant="accent" size="lg">
                Daftar sekarang
            </x-site.btn>
            <x-site.btn :href="$waUrl" variant="light" size="lg" target="_blank" rel="noopener">
                Tanya via WhatsApp
            </x-site.btn>
        </div>
    </div>
</x-site.section>
