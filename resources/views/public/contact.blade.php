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

                    {{-- Urutan & lebar kolom mengikuti form Data Murid & Wali di panel admin,
                         supaya isian yang sama tampil di posisi yang sama di kedua halaman. --}}
                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label for="child_name" class="tac-label">
                                Nama anak <span class="tac-text-coral" aria-hidden="true">*</span>
                            </label>
                            <input type="text" id="child_name" name="child_name" required maxlength="100"
                                   value="{{ old('child_name') }}" placeholder="Nama lengkap anak" class="tac-input">
                        </div>
                        <div class="col-md-6">
                            <label for="date_of_birth" class="tac-label">
                                Tanggal lahir <span class="tac-text-coral" aria-hidden="true">*</span>
                            </label>
                            <input type="date" id="date_of_birth" name="date_of_birth" required max="{{ now()->toDateString() }}"
                                   value="{{ old('date_of_birth') }}" class="tac-input">
                        </div>
                        <div class="col-md-6">
                            <label for="child_age" class="tac-label">
                                Usia <span class="fw-normal tac-muted-soft">(opsional)</span>
                            </label>
                            <input type="number" id="child_age" name="child_age" min="2" max="17"
                                   value="{{ old('child_age') }}" placeholder="7" class="tac-input">
                        </div>
                        <div class="col-md-6">
                            <label for="parent_name" class="tac-label">
                                Nama orang tua / wali <span class="tac-text-coral" aria-hidden="true">*</span>
                            </label>
                            <input type="text" id="parent_name" name="parent_name" required maxlength="100"
                                   value="{{ old('parent_name') }}" placeholder="Nama Anda" class="tac-input">
                        </div>
                        <div class="col-md-6">
                            <label for="parent_phone" class="tac-label">
                                Nomor WhatsApp <span class="tac-text-coral" aria-hidden="true">*</span>
                            </label>
                            <input type="tel" id="parent_phone" name="parent_phone" required maxlength="25"
                                   value="{{ old('parent_phone') }}" placeholder="0812 3456 7890" class="tac-input">
                        </div>
                        <div class="col-md-6">
                            <label for="parent_email" class="tac-label">
                                Email <span class="tac-text-coral" aria-hidden="true">*</span>
                            </label>
                            <input type="email" id="parent_email" name="parent_email" required maxlength="150"
                                   value="{{ old('parent_email') }}" placeholder="nama@email.com" class="tac-input">
                        </div>
                        <div class="col-md-6">
                            <label for="class_type" class="tac-label">
                                Tipe kelas <span class="tac-text-coral" aria-hidden="true">*</span>
                            </label>
                            <select id="class_type" name="class_type" required class="tac-input">
                                <option value="">— Pilih tipe kelas —</option>
                                @foreach(\App\Models\Lead::CLASS_TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('class_type', $selectedType) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="program" class="tac-label">
                                Kelas yang diminati <span class="tac-text-coral" aria-hidden="true">*</span>
                            </label>
                            <select id="program" name="program" required class="tac-input">
                                <option value="">— Pilih kelas —</option>
                                @foreach($classOptions as $option)
                                    <option value="{{ $option['value'] }}" @selected(old('program', $selected) === $option['value']) data-category="{{ $option['category'] }}">
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="tac-muted-soft mt-2 mb-0" style="font-size: 0.75rem;" data-class-hint hidden></p>
                        </div>
                        <div class="col-12">
                            <label for="address" class="tac-label">
                                Alamat <span class="tac-text-coral" aria-hidden="true">*</span>
                            </label>
                            <textarea id="address" name="address" rows="2" required maxlength="500" class="tac-input"
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

    // Tipe kelas → menyaring daftar "Kelas yang diminati". Opsi disimpan sekali
    // di awal, lalu select dibangun ulang tiap tipe berubah (option[hidden]
    // tidak konsisten di semua browser, terutama di ponsel).
    var typeSelect = form.elements['class_type'];
    var programSelect = form.elements['program'];
    var hint = form.querySelector('[data-class-hint]');

    if (typeSelect && programSelect) {
        var placeholder = programSelect.options[0].text;
        var allClasses = Array.prototype.slice.call(programSelect.options, 1).map(function (option) {
            return {
                value: option.value,
                label: option.text.trim(),
                // Kelas tanpa kategori (mis. Holiday Class) cocok untuk semua tipe.
                category: option.dataset.category || ''
            };
        });

        function renderClasses() {
            var type = typeSelect.value;
            var previous = programSelect.value;
            var matches = allClasses.filter(function (item) {
                return !type || !item.category || item.category === type;
            });

            programSelect.innerHTML = '';
            programSelect.add(new Option(placeholder, ''));
            matches.forEach(function (item) {
                programSelect.add(new Option(item.label, item.value));
            });

            // Pertahankan pilihan lama bila masih relevan dengan tipe baru.
            programSelect.value = matches.some(function (item) { return item.value === previous; })
                ? previous
                : '';

            // Tipe kelas yang belum punya jadwal tidak boleh membuat form buntu:
            // pilihan kelas dilonggarkan, sisi server memakai aturan yang sama.
            var empty = type && matches.length === 0;
            programSelect.required = !empty;

            if (hint) {
                hint.hidden = !empty;
                hint.textContent = empty
                    ? 'Belum ada jadwal kelas ' + selectedLabel('class_type').toLowerCase() + '. Kirim saja formnya, admin akan mengabari begitu kelas dibuka.'
                    : '';
            }
        }

        // Kebalikannya: memilih kelas ikut mengisi tipe kelas yang belum diisi,
        // supaya data yang masuk ke admin tidak saling bertentangan.
        programSelect.addEventListener('change', function () {
            if (typeSelect.value || !programSelect.value) return;

            var picked = allClasses.filter(function (item) { return item.value === programSelect.value; })[0];
            if (picked && picked.category) typeSelect.value = picked.category;
        });

        typeSelect.addEventListener('change', renderClasses);
        renderClasses();
    }

    // Tanggal lahir → usia, supaya orang tua tidak perlu menghitung sendiri.
    var birthField = form.elements['date_of_birth'];
    if (birthField) {
        birthField.addEventListener('change', function () {
            // Dipecah manual: new Date('2018-05-17') dibaca sebagai UTC, bisa
            // meleset sehari saat dibandingkan dengan tanggal lokal.
            var parts = birthField.value.split('-');
            if (parts.length !== 3) return;

            var year = +parts[0], month = +parts[1], day = +parts[2];
            var today = new Date();
            var age = today.getFullYear() - year;
            var beforeBirthday = today.getMonth() + 1 < month
                || (today.getMonth() + 1 === month && today.getDate() < day);
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
