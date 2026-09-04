@php
    $contact = config('site.contact');
    $credit = config('site.credit');
@endphp

<footer class="tac-bg-ink tac-text-paper pt-5 pb-4">
    <div class="container">
        <div class="row g-5">

            {{-- Brand --}}
            <div class="col-lg-5">
                <div class="d-flex align-items-center gap-2">
                    <span class="tac-icon tac-logo-mark border-0" aria-hidden="true">
                        <img src="{{ asset('images/ogo.jpg') }}" alt="" width="48" height="48"
                             loading="lazy" decoding="async">
                    </span>
                    <span class="tac-display fs-4 fw-bolder tac-text-paper">Tarakan Art Class</span>
                </div>
                <p class="mt-3 mb-4 small lh-lg tac-muted-invert" style="max-width: 26rem;">
                    {{ config('site.description') }}
                </p>

                <div class="d-flex gap-2">
                    <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener"
                       class="tac-icon tac-icon-sm tac-icon-brand tac-icon-wa border-0 text-decoration-none"
                       aria-label="WhatsApp Tarakan Art Class">
                        <img src="{{ asset('images/whatsapp.png') }}" alt="" width="40" height="40"
                             loading="lazy" decoding="async" aria-hidden="true">
                    </a>
                    <a href="https://instagram.com/{{ $contact['instagram'] }}" target="_blank" rel="noopener"
                       class="tac-icon tac-icon-sm tac-icon-brand tac-icon-ig border-0 text-decoration-none"
                       aria-label="Instagram Tarakan Art Class">
                        <img src="{{ asset('images/instagram.png') }}" alt="" width="40" height="40"
                             loading="lazy" decoding="async" aria-hidden="true">
                    </a>
                </div>
            </div>

            {{-- Menu --}}
            <div class="col-6 col-lg-3">
                <h2 class="tac-display fs-6 fw-bold tac-text-paper">Jelajahi</h2>
                <ul class="list-unstyled mt-3 mb-0 d-grid gap-2 small">
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.about') }}">Tentang kami</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.programs') }}">Program &amp; Kelas</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.gallery') }}">Galeri karya</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.schedule') }}">Jadwal kelas</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.contact') }}">Kontak &amp; Pendaftaran</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('reports.guest') }}">Cek raport anak</a></li>
                </ul>
            </div>

            {{-- Kontak --}}
            <div class="col-6 col-lg-4">
                <h2 class="tac-display fs-6 fw-bold tac-text-paper">Hubungi kami</h2>
                <ul class="list-unstyled mt-3 mb-0 d-grid gap-2 small tac-muted-invert">
                    <li>{{ $contact['address'] }}</li>
                    <li>
                        <a class="text-decoration-none tac-muted-invert" href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener">
                            {{ $contact['whatsapp_display'] }}
                        </a>
                    </li>
                    <li><a class="text-decoration-none tac-muted-invert" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
                    <li>
                        <a class="text-decoration-none tac-muted-invert" href="https://instagram.com/{{ $contact['instagram'] }}" target="_blank" rel="noopener">
                            &#64;{{ $contact['instagram'] }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-5 pt-4"
             style="border-top: 1px solid rgba(255, 251, 243, 0.14);">
            <p class="mb-0 tac-muted-invert" style="font-size: 0.8rem;">
                &copy; {{ date('Y') }} Tarakan Art Class. Seluruh hak cipta dilindungi.
            </p>
            <p class="mb-0 d-flex align-items-center gap-2 tac-muted-invert" style="font-size: 0.8rem;">
                <a class="text-decoration-none tac-muted-invert" href="{{ route('login') }}">Login admin</a>
            </p>
        </div>
    </div>
</footer>
