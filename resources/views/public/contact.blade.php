@extends('layouts.public')

@section('title', 'Kontak & Pendaftaran')
@section('description', 'Alamat studio, jam operasional, dan form pendaftaran Tarakan Art Class. Kirim data anak, admin kami akan menghubungi Anda lewat WhatsApp.')

@section('content')

@php
    $contact = config('site.contact');
    $waText = 'Halo '.config('site.name').', saya ingin menanyakan kelas untuk anak saya.';
    $waUrl = 'https://wa.me/'.$contact['whatsapp'].'?text='.rawurlencode($waText);
    $mapsQuery = urlencode($contact['maps_query']);
@endphp

<x-site.section tone="paper-2">
    <x-site.heading
        level="h1"
        eyebrow="Kontak & Pendaftaran"
        title="Mari mulai dari perkenalan"
        subtitle="Isi form di bawah dan admin kami akan menghubungi Anda untuk mengatur jadwal kelas percobaan. Ingin langsung mengobrol? Sapa kami di WhatsApp." />
</x-site.section>

<x-site.section tone="paper">
    <div class="row g-4 g-lg-5">

        {{-- ─── Info kontak ───────────────────────────────────────── --}}
        <aside class="col-lg-5">
            <div class="tac-card p-4">
                <h2 class="fs-4 mb-0">Studio kami</h2>

                <dl class="d-grid gap-4 small mt-4 mb-0">
                    <div>
                        <dt class="tac-display fw-bold">Alamat</dt>
                        <dd class="tac-muted mt-1 mb-0">{{ $contact['address'] }}</dd>
                    </div>
                    <div>
                        <dt class="tac-display fw-bold">WhatsApp</dt>
                        <dd class="mt-1 mb-0">
                            <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="tac-text-coral">{{ $contact['whatsapp_display'] }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="tac-display fw-bold">Email</dt>
                        <dd class="mt-1 mb-0"><a href="mailto:{{ $contact['email'] }}" class="tac-text-coral">{{ $contact['email'] }}</a></dd>
                    </div>
                    <div>
                        <dt class="tac-display fw-bold">Instagram</dt>
                        <dd class="mt-1 mb-0">
                            <a href="https://instagram.com/{{ $contact['instagram'] }}" target="_blank" rel="noopener" class="tac-text-coral">
                                &#64;{{ $contact['instagram'] }}
                            </a>
                        </dd>
                    </div>
                </dl>

                <h3 class="tac-dashed-top fs-5 mt-4 pt-4">Jam operasional</h3>
                <dl class="d-grid gap-2 small mt-3 mb-0">
                    @foreach($hours as $row)
                        <div class="d-flex justify-content-between gap-3">
                            <dt class="fw-normal tac-muted-soft">{{ $row['day'] }}</dt>
                            <dd class="mb-0 text-end fw-semibold">{{ $row['time'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Peta lokasi (embed tanpa API key) --}}
            <div class="tac-card overflow-hidden mt-4">
                <iframe
                    title="Peta lokasi {{ config('site.name') }}"
                    src="https://www.google.com/maps?q={{ $mapsQuery }}&output=embed"
                    class="w-100 border-0 d-block"
                    style="height: 16rem;"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen></iframe>
            </div>
        </aside>

        {{-- ─── Form pendaftaran ──────────────────────────────────── --}}
        <div class="col-lg-7">

            @if(session('lead_sent'))
                <div class="tac-card tac-bg-leaf-soft p-4 p-sm-5 mb-4" role="status">
                    <span class="tac-icon tac-bg-leaf" aria-hidden="true">✅</span>
                    <h2 class="fs-3 mt-3 mb-2">Terima kasih!</h2>
                    <p class="small lh-lg tac-muted mb-2">
                        Data <strong>{{ session('lead_sent') }}</strong> sudah kami terima. Admin akan menghubungi Anda
                        di jam operasional untuk mengatur jadwal kelas percobaan.
                    </p>
                    <p class="small tac-muted-soft mb-0">
                        Tab WhatsApp berisi data di atas sudah kami buka. Tidak muncul? Buka lewat tombol ini.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                        <x-site.btn :href="session('lead_whatsapp', $waUrl)" target="_blank" rel="noopener">
                            Lanjut chat WhatsApp
                        </x-site.btn>
                        <x-site.btn :href="route('public.programs')" variant="ghost">Lihat program lain</x-site.btn>
                    </div>
                </div>
            @endif

            <div class="tac-card p-4 p-sm-5">
                <h2 class="fs-4 mb-2">Form pendaftaran</h2>
                <p class="small tac-muted mb-0">
                    Setelah menekan <strong>Kirim pendaftaran</strong>, WhatsApp admin akan terbuka dengan
                    data yang Anda isi. Pendaftaran final tetap diproses admin, tidak ada pembayaran di halaman ini.
                </p>

                @if($errors->any())
                    <div class="rounded-3 px-4 py-3 mt-4 small" role="alert"
                         style="border: 2px solid var(--tac-coral); background-color: rgba(255, 107, 94, 0.1);">
                        <p class="tac-display fw-bold mb-1">Ada yang perlu diperbaiki:</p>
                        <ul class="mb-0 ps-3 tac-muted">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('public.contact.store') }}" class="mt-4"
                      id="lead-form"
                      data-wa-number="{{ $contact['whatsapp'] }}"
                      data-wa-greeting="Halo {{ config('site.name') }}, saya ingin mendaftarkan anak saya.">
                    @csrf

                    {{-- Honeypot: disembunyikan dari manusia, diisi bot. --}}
                    <div class="d-none" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <fieldset class="border-0 p-0 m-0">
                        <legend class="tac-legend">Data anak</legend>

                        <div class="row g-3 mt-0">
                            <div class="col-sm-8">
                                <label for="child_name" class="tac-label">
                                    Nama anak <span class="tac-text-coral" aria-hidden="true">*</span>
                                </label>
                                <input type="text" id="child_name" name="child_name" required maxlength="100"
                                       value="{{ old('child_name') }}" placeholder="Nama lengkap anak" class="tac-input">
                            </div>
                            <div class="col-sm-4">
                                <label for="child_age" class="tac-label">Usia</label>
                                <input type="number" id="child_age" name="child_age" min="2" max="17"
                                       value="{{ old('child_age') }}" placeholder="7" class="tac-input">
                            </div>
                            <div class="col-sm-6">
                                <label for="date_of_birth" class="tac-label">
                                    Tanggal lahir <span class="fw-normal tac-muted-soft">(opsional)</span>
                                </label>
                                <input type="date" id="date_of_birth" name="date_of_birth" max="{{ now()->toDateString() }}"
                                       value="{{ old('date_of_birth') }}" class="tac-input">
                            </div>
                            <div class="col-sm-6">
                                <label for="class_type" class="tac-label">Tipe kelas</label>
                                <select id="class_type" name="class_type" class="tac-input">
                                    <option value="">Belum tahu — bantu pilihkan</option>
                                    @foreach(\App\Models\Lead::CLASS_TYPES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('class_type', $selectedType) === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="program" class="tac-label">Kelas yang diminati</label>
                                <select id="program" name="program" class="tac-input">
                                    <option value="">Belum tahu — bantu pilihkan</option>
                                    @foreach($classOptions as $option)
                                        <option value="{{ $option['value'] }}" @selected(old('program', $selected) === $option['value'])>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="tac-dashed-top border-0 p-0 m-0 mt-4 pt-4">
                        <legend class="tac-legend">Data orang tua / wali</legend>

                        <div class="row g-3 mt-0">
                            <div class="col-sm-6">
                                <label for="parent_name" class="tac-label">
                                    Nama orang tua <span class="tac-text-coral" aria-hidden="true">*</span>
                                </label>
                                <input type="text" id="parent_name" name="parent_name" required maxlength="100"
                                       value="{{ old('parent_name') }}" placeholder="Nama Anda" class="tac-input">
                            </div>
                            <div class="col-sm-6">
                                <label for="parent_phone" class="tac-label">
                                    Nomor WhatsApp <span class="tac-text-coral" aria-hidden="true">*</span>
                                </label>
                                <input type="tel" id="parent_phone" name="parent_phone" required maxlength="25"
                                       value="{{ old('parent_phone') }}" placeholder="0812 3456 7890" class="tac-input">
                            </div>
                            <div class="col-12">
                                <label for="parent_email" class="tac-label">
                                    Email <span class="fw-normal tac-muted-soft">(opsional)</span>
                                </label>
                                <input type="email" id="parent_email" name="parent_email" maxlength="150"
                                       value="{{ old('parent_email') }}" placeholder="nama@email.com" class="tac-input">
                            </div>
                            <div class="col-12">
                                <label for="address" class="tac-label">
                                    Alamat <span class="fw-normal tac-muted-soft">(opsional)</span>
                                </label>
                                <textarea id="address" name="address" rows="2" maxlength="500" class="tac-input"
                                          placeholder="Nama jalan, nomor rumah, kelurahan">{{ old('address') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label for="message" class="tac-label">
                                    Pesan <span class="fw-normal tac-muted-soft">(opsional)</span>
                                </label>
                                <textarea id="message" name="message" rows="4" maxlength="1000" class="tac-input"
                                          placeholder="Misalnya: anak saya belum pernah ikut kelas seni, apakah bisa coba dulu?">{{ old('message') }}</textarea>
                            </div>
                        </div>
                    </fieldset>

                    <div class="tac-dashed-top d-flex flex-column flex-sm-row align-items-sm-center gap-3 mt-4 pt-4">
                        <x-site.btn type="submit" variant="coral" size="lg">Kirim pendaftaran</x-site.btn>
                        <p class="tac-muted-soft mb-0" style="font-size: 0.75rem;">
                            Data hanya dipakai untuk menghubungi Anda soal kelas.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-site.section>

@push('scripts')
<script>
// Saat form dikirim: buka chat WhatsApp admin yang sudah terisi ringkasan
// isian, lalu biarkan form tetap ter-submit ke server agar leadnya tersimpan.
(function () {
    var form = document.getElementById('lead-form');
    if (!form) return;

    function value(name) {
        var field = form.elements[name];
        return field ? field.value.trim() : '';
    }

    function selectedLabel(name) {
        var select = form.elements[name];
        if (!select || !select.value) return '';
        return select.options[select.selectedIndex].text.trim();
    }

    // Tanggal lahir → usia, supaya orang tua tidak perlu menghitung sendiri.
    var birthField = form.elements['date_of_birth'];
    if (birthField) {
        birthField.addEventListener('change', function () {
            if (!birthField.value) return;

            var born = new Date(birthField.value);
            if (isNaN(born.getTime())) return;

            var today = new Date();
            var age = today.getFullYear() - born.getFullYear();
            var beforeBirthday = today.getMonth() < born.getMonth()
                || (today.getMonth() === born.getMonth() && today.getDate() < born.getDate());
            if (beforeBirthday) age--;

            if (age >= 0 && age < 120) form.elements['child_age'].value = age;
        });
    }

    form.addEventListener('submit', function () {
        var age = value('child_age');
        var birthDate = value('date_of_birth');

        var lines = [
            ['Nama anak', value('child_name')],
            ['Usia', age ? age + ' tahun' : ''],
            ['Tanggal lahir', birthDate ? birthDate.split('-').reverse().join('/') : ''],
            ['Tipe kelas', selectedLabel('class_type')],
            ['Kelas yang diminati', selectedLabel('program')],
            ['Nama orang tua', value('parent_name')],
            ['Nomor WhatsApp', value('parent_phone')],
            ['Email', value('parent_email')],
            ['Alamat', value('address')],
            ['Pesan', value('message')]
        ].filter(function (row) {
            return row[1] !== '';
        }).map(function (row) {
            return row[0] + ': ' + row[1];
        });

        var text = form.dataset.waGreeting + '\n\n' + lines.join('\n');

        window.open(
            'https://wa.me/' + form.dataset.waNumber + '?text=' + encodeURIComponent(text),
            '_blank',
            'noopener'
        );
    });
})();
</script>
@endpush

@endsection
