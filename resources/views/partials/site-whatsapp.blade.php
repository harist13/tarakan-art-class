@php
    $waText = 'Halo '.config('site.name').', saya ingin bertanya tentang kelas seni untuk anak saya.';
    $waUrl = 'https://wa.me/'.config('site.contact.whatsapp').'?text='.rawurlencode($waText);
@endphp

{{-- Tombol WhatsApp mengambang — tampil di semua halaman publik. --}}
<a href="{{ $waUrl }}" target="_blank" rel="noopener" class="tac-wa-float" aria-label="Chat via WhatsApp">
    <span class="tac-wa-pulse tac-icon tac-icon-sm tac-bg-ink border-0 tac-text-paper rounded-circle" aria-hidden="true"
          style="width: 2rem; height: 2rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm4.52 11.99c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.84-.2-.48-.4-.42-.55-.43h-.47c-.16 0-.43.06-.65.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.2 3.72.59.25 1.05.4 1.4.52.59.19 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.08.15-1.18-.06-.11-.23-.17-.48-.29Z"/>
        </svg>
    </span>
    <span class="tac-display fw-bold d-none d-sm-inline" style="font-size: 0.9rem;">Chat Admin</span>
</a>
