@extends('layouts.public')

@section('title', 'Jadwal Kelas')
@section('description', 'Jadwal kelas Tarakan Art Class tiga minggu ke depan, informasi kelas pengganti (replacement class), hari libur, dan pengumuman Holiday Class.')

@section('content')

@php
    // Nama hari & bulan dalam bahasa Indonesia — tidak bergantung pada locale Carbon
    // supaya tampilan sistem admin (locale bawaan) tidak ikut berubah.
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $tanggalID = fn ($date) => $hari[(int) $date->format('w')].', '.$date->format('j').' '.$bulan[(int) $date->format('n')].' '.$date->format('Y');

    $holidayDates = $holidays->pluck('date')->map(fn ($d) => $d->toDateString())->all();
    $categoryLabels = collect(config('site.programs'))->pluck('name', 'category');
@endphp

<x-site.section tone="paper-2" :paint="true">
    <x-site.heading
        level="h1"
        eyebrow="Jadwal"
        title="Jadwal kelas & pengumuman"
        subtitle="Jadwal umum mingguan, slot tiga minggu ke depan, dan info kelas pengganti. Perubahan mendadak selalu kami kabari lewat WhatsApp grup wali." />
</x-site.section>

{{-- ─── Jadwal umum mingguan ───────────────────────────────────── --}}
<x-site.section tone="paper">
    <x-site.heading
        align="left"
        title="Jadwal umum per program"
        subtitle="Hari & jam disusun otomatis dari slot yang terdaftar di sistem kami. Slot pasti per angkatan dikonfirmasi admin saat pendaftaran." />

    <div class="table-responsive mt-4">
        <table class="tac-table w-100 mb-0" style="min-width: 38rem;">
            <caption class="visually-hidden">Jadwal umum mingguan per program</caption>
            <thead>
                <tr>
                    <th scope="col">Program</th>
                    <th scope="col">Usia</th>
                    <th scope="col">Jadwal umum</th>
                    <th scope="col">Durasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($programs as $program)
                    <tr>
                        <th scope="row" class="tac-display fw-bold">{{ $program['name'] }}</th>
                        <td class="tac-muted">{{ $program['age'] }}</td>
                        <td class="tac-muted">
                            {{ $program['schedule'] }}
                            {{-- Tanpa slot di sistem, yang tampil adalah jadwal rutin perkiraan. --}}
                            @unless($program['is_live'])
                                <span class="d-block tac-muted-soft" style="font-size: 0.75rem;">
                                    Perkiraan — belum ada slot terjadwal, tanyakan admin.
                                </span>
                            @endunless
                        </td>
                        <td class="tac-muted">{{ $program['duration'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-site.section>

{{-- ─── Slot 3 minggu ke depan (live dari sistem) ──────────────── --}}
<x-site.section tone="paper-2">
    <x-site.heading
        align="left"
        title="Kelas terjadwal"
        :subtitle="'Slot kelas sampai '.$tanggalID($until).'. Diperbarui otomatis dari sistem kami.'" />

    @if($days->isNotEmpty())
        <div class="d-grid gap-4 mt-4">
            @foreach($days as $date => $classes)
                @php $carbonDate = $classes->first()->schedule_date; @endphp
                <div class="tac-card overflow-hidden">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 tac-bg-paper-2 px-4 py-3"
                         style="border-bottom: 1px solid var(--tac-line);">
                        <h3 class="fs-6 mb-0">{{ $tanggalID($carbonDate) }}</h3>
                        @if(in_array($date, $holidayDates, true))
                            <span class="tac-badge tac-bg-sun fw-bold">Hari libur</span>
                        @endif
                    </div>

                    <ul class="list-unstyled mb-0">
                        @foreach($classes as $class)
                            @php
                                $isHoliday = in_array($date, $holidayDates, true);
                                $seats = $class->remainingSeats();
                            @endphp
                            <li class="d-flex flex-wrap align-items-center gap-3 px-4 py-3"
                                @style(['border-bottom: 2px dashed rgba(36, 27, 54, 0.12)' => ! $loop->last])>
                                <span class="tac-display fw-bold flex-shrink-0" style="width: 3.25rem;">
                                    {{ substr($class->schedule_time, 0, 5) }}
                                </span>

                                <span class="flex-grow-1" style="min-width: 0;">
                                    <span class="d-block fw-semibold text-truncate">{{ $class->class_name }}</span>
                                    <span class="d-block tac-muted-soft" style="font-size: 0.75rem;">
                                        {{ $categoryLabels[$class->class_category] ?? ucfirst($class->class_category) }}
                                        @if($class->tutor) &middot; {{ $class->tutor->name }} @endif
                                    </span>
                                </span>

                                @if($isHoliday)
                                    <span class="tac-badge tac-muted" style="background-color: rgba(36, 27, 54, 0.08);">Ditiadakan</span>
                                @elseif($class->status === 'closed')
                                    <span class="tac-badge tac-muted" style="background-color: rgba(36, 27, 54, 0.08);">Ditutup</span>
                                @elseif($seats > 0)
                                    <span class="tac-badge tac-bg-leaf-soft">{{ $seats }} kursi tersisa</span>
                                @else
                                    <span class="tac-badge tac-bg-coral-soft">Penuh</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @else
        <div class="tac-card text-center px-4 py-5 mt-4">
            <span class="tac-dashed-box tac-thumb-placeholder mx-auto" style="width: 4rem; height: 4rem;" aria-hidden="true">📅</span>
            <p class="tac-display fs-5 fw-bolder mt-4 mb-2">Belum ada slot terjadwal</p>
            <p class="small tac-muted mx-auto mb-0" style="max-width: 28rem;">
                Jadwal angkatan berikutnya sedang kami susun. Hubungi admin untuk mendapatkan
                jadwal terbaru lebih dulu.
            </p>
            <div class="mt-4">
                <x-site.btn :href="route('public.contact')" size="sm">Tanya jadwal</x-site.btn>
            </div>
        </div>
    @endif
</x-site.section>

{{-- ─── Libur, replacement, pengumuman ─────────────────────────── --}}
<x-site.section tone="paper">
    <div class="row row-cols-1 row-cols-lg-3 g-4">

        {{-- Kelas pengganti --}}
        <div class="col">
            <div class="tac-card h-100 p-4">
                <span class="tac-icon tac-bg-sky tac-text-paper" aria-hidden="true">🔁</span>
                <h2 class="fs-4 mt-4 mb-3">Kelas pengganti</h2>
                <p class="small lh-lg tac-muted mb-0">
                    Anak berhalangan hadir? Kabari admin sebelum jadwal kelas, dan kami carikan
                    slot pengganti pada kelas dengan kategori yang sama.
                </p>
                <ul class="list-unstyled d-grid gap-2 small tac-muted mt-3 mb-0">
                    <li class="d-flex gap-2"><span class="tac-text-leaf" aria-hidden="true">✓</span> Tanpa biaya tambahan</li>
                    <li class="d-flex gap-2"><span class="tac-text-leaf" aria-hidden="true">✓</span> Menyesuaikan sisa kursi</li>
                    <li class="d-flex gap-2"><span class="tac-text-leaf" aria-hidden="true">✓</span> Slot penuh &amp; hari libur tidak bisa dipakai</li>
                </ul>
            </div>
        </div>

        {{-- Hari libur --}}
        <div class="col">
            <div class="tac-card h-100 p-4">
                <span class="tac-icon tac-bg-sun" aria-hidden="true">🏖️</span>
                <h2 class="fs-4 mt-4 mb-3">Hari libur</h2>
                @if($holidays->isNotEmpty())
                    <ul class="list-unstyled mb-0">
                        @foreach($holidays as $holiday)
                            <li class="d-flex justify-content-between align-items-start gap-3 pb-3 mb-3"
                                @style(['border-bottom: 2px dashed rgba(36, 27, 54, 0.12)' => ! $loop->last])>
                                <span class="small tac-muted">{{ $holiday->name ?: 'Kelas ditiadakan' }}</span>
                                <span class="fw-semibold text-end flex-shrink-0" style="font-size: 0.75rem;">
                                    {{ $holiday->date->format('j') }} {{ $bulan[(int) $holiday->date->format('n')] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="small lh-lg tac-muted mb-0">
                        Tidak ada hari libur dalam tiga minggu ke depan. Semua kelas berjalan sesuai jadwal.
                    </p>
                @endif
            </div>
        </div>

        {{-- Pengumuman / Holiday Class --}}
        <div class="col">
            <div class="tac-card h-100 p-4">
                <span class="tac-icon tac-bg-coral tac-text-paper" aria-hidden="true">📢</span>
                <h2 class="fs-4 mt-4 mb-3">Pengumuman</h2>
                @if($announcements->isNotEmpty())
                    <ul class="list-unstyled mb-0">
                        @foreach($announcements as $event)
                            <li class="pb-3 mb-3" @style(['border-bottom: 2px dashed rgba(36, 27, 54, 0.12)' => ! $loop->last])>
                                <p class="small fw-semibold mb-1">{{ $event->title }}</p>
                                <p class="tac-muted-soft mb-0" style="font-size: 0.75rem;">
                                    {{ $tanggalID($event->date) }}
                                    @unless($event->isAllDay()) &middot; {{ substr($event->start_time, 0, 5) }} WITA @endunless
                                </p>
                                @if($event->description)
                                    <p class="lh-lg tac-muted mt-1 mb-0" style="font-size: 0.75rem;">{{ $event->description }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="small lh-lg tac-muted mb-0">
                        Belum ada agenda khusus. Pengumuman Holiday Class dan pameran karya akan tampil di sini.
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-site.section>

<x-site.cta
    title="Butuh jadwal yang lebih fleksibel?"
    subtitle="Ceritakan kesibukan anak Anda, kami bantu carikan slot kelas yang paling pas." />

@endsection
