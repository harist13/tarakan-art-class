{{-- Catatan jumlah data yang terdampak tunggakan. Datanya TIDAK disembunyikan —
     catatan ini hanya memberi tahu admin mana yang perlu ditagih.
     Pakai: @include('partials.arrears-note', ['count' => $withheldCount, 'label' => 'raport', 'effect' => '...']) --}}
@if(($count ?? 0) > 0)
    <div class="alert alert-light border small d-flex align-items-start gap-2 py-2">
        <i class="bi bi-cash-coin text-warning mt-1"></i>
        <div>
            <strong>{{ $count }} {{ $label }}</strong> milik murid yang punya invoice <strong>lewat jatuh tempo</strong>. {{ $effect }}
            Datanya tetap tampil di sini. Rincian per murid ada di <a href="{{ route('students.index') }}" class="alert-link">Data murid &amp; wali</a>;
            lunasi tunggakannya di <a href="{{ route('payments.index') }}" class="alert-link">menu Pembayaran</a> dan kuncinya terbuka otomatis.
        </div>
    </div>
@endif
