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
                    <span class="tac-icon tac-bg-coral border-0" aria-hidden="true">🎨</span>
                    <span class="tac-display fs-4 fw-bolder tac-text-paper">Tarakan Art Class</span>
                </div>
                <p class="mt-3 mb-4 small lh-lg tac-muted-invert" style="max-width: 26rem;">
                    {{ config('site.description') }}
                </p>

                <div class="d-flex gap-2">
                    <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener"
                       class="tac-icon tac-icon-sm tac-bg-leaf border-0 tac-text-ink text-decoration-none"
                       aria-label="WhatsApp Tarakan Art Class">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm4.52 11.99c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.84-.2-.48-.4-.42-.55-.43h-.47c-.16 0-.43.06-.65.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.2 3.72.59.25 1.05.4 1.4.52.59.19 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.08.15-1.18-.06-.11-.23-.17-.48-.29Z"/>
                        </svg>
                    </a>
                    <a href="https://instagram.com/{{ $contact['instagram'] }}" target="_blank" rel="noopener"
                       class="tac-icon tac-icon-sm tac-bg-grape border-0 tac-text-paper text-decoration-none"
                       aria-label="Instagram Tarakan Art Class">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.8 3.8 0 0 1-1.38-.9 3.8 3.8 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16Zm0 1.98c-3.14 0-3.51.01-4.75.07-1.15.05-1.77.24-2.18.4-.55.21-.94.47-1.35.88-.41.41-.67.8-.88 1.35-.16.41-.35 1.03-.4 2.18-.06 1.24-.07 1.61-.07 4.75s.01 3.51.07 4.75c.05 1.15.24 1.77.4 2.18.21.55.47.94.88 1.35.41.41.8.67 1.35.88.41.16 1.03.35 2.18.4 1.24.06 1.61.07 4.75.07s3.51-.01 4.75-.07c1.15-.05 1.77-.24 2.18-.4.55-.21.94-.47 1.35-.88.41-.41.67-.8.88-1.35.16-.41.35-1.03.4-2.18.06-1.24.07-1.61.07-4.75s-.01-3.51-.07-4.75c-.05-1.15-.24-1.77-.4-2.18a3.63 3.63 0 0 0-.88-1.35 3.63 3.63 0 0 0-1.35-.88c-.41-.16-1.03-.35-2.18-.4-1.24-.06-1.61-.07-4.75-.07Zm0 3.37a5.49 5.49 0 1 1 0 10.98 5.49 5.49 0 0 1 0-10.98Zm0 9.05a3.56 3.56 0 1 0 0-7.12 3.56 3.56 0 0 0 0 7.12Zm6.99-9.27a1.28 1.28 0 1 1-2.56 0 1.28 1.28 0 0 1 2.56 0Z"/>
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Menu --}}
            <div class="col-6 col-lg-3">
                <h2 class="tac-display fs-6 fw-bold tac-text-paper">Jelajahi</h2>
                <ul class="list-unstyled mt-3 mb-0 d-grid gap-2 small">
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.about') }}">Tentang Kami</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.programs') }}">Program &amp; Kelas</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.gallery') }}">Galeri Karya</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.schedule') }}">Jadwal Kelas</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('public.contact') }}">Kontak &amp; Pendaftaran</a></li>
                    <li><a class="text-decoration-none tac-muted-invert" href="{{ route('reports.guest') }}">Cek Raport Anak</a></li>
                </ul>
            </div>

            {{-- Kontak --}}
            <div class="col-6 col-lg-4">
                <h2 class="tac-display fs-6 fw-bold tac-text-paper">Hubungi Kami</h2>
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
             style="border-top: 1px solid rgba(253, 250, 243, 0.15);">
            <p class="mb-0 tac-muted-invert" style="font-size: 0.8rem;">
                &copy; {{ date('Y') }} Tarakan Art Class. Seluruh hak cipta dilindungi.
            </p>
            <p class="mb-0 d-flex align-items-center gap-2 tac-muted-invert" style="font-size: 0.8rem;">
                <a class="text-decoration-none tac-muted-invert" href="{{ route('login') }}">Login Admin</a>
                <span aria-hidden="true">&middot;</span>
                <span>
                    Dibangun oleh
                    <a class="text-decoration-none fw-semibold tac-text-paper" href="{{ $credit['url'] }}" target="_blank" rel="noopener">{{ $credit['label'] }}</a>
                </span>
            </p>
        </div>
    </div>
</footer>
