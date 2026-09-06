{{--
    Daftar kelas sebagai data terstruktur Course, dipasang di halaman /program.

    Harga dan durasi di config/site.php ditulis untuk dibaca orang
    ("Rp250.000 / bulan", "60 menit / pertemuan"), sementara schema.org menuntut
    angka dan durasi ISO 8601. Penerjemahannya dilakukan di sini supaya
    config tetap enak dibaca dan tidak perlu ada dua sumber harga.
--}}
@php
    $orgId = route('public.home').'#organization';

    // "Rp250.000 / bulan" → 250000. Titik ribuan dibuang, angka pertama diambil.
    $harga = function (?string $label): ?string {
        if (! $label || ! preg_match('/[\d.]+/', str_replace('Rp', '', $label), $m)) {
            return null;
        }

        return str_replace('.', '', $m[0]);
    };

    // "60 menit / pertemuan" → PT60M, "2 jam / sesi" → PT2H.
    $durasi = function (?string $label): ?string {
        if (! $label || ! preg_match('/(\d+)\s*(menit|jam)/i', $label, $m)) {
            return null;
        }

        return 'PT'.$m[1].(strtolower($m[2]) === 'jam' ? 'H' : 'M');
    };

    // "3 – 5 tahun" → "3-5". Tanda pisahnya en dash, bukan hyphen.
    $usia = fn (?string $label) => $label
        ? trim(str_replace(['–', ' tahun'], ['-', ''], $label))
        : null;

    $courses = collect($programs)->map(fn (array $p) => array_filter([
        '@type' => 'Course',
        'name' => $p['name'],
        'description' => $p['summary'],
        'url' => route('public.programs').'#'.($p['slug'] ?? ''),
        'inLanguage' => 'id-ID',
        'typicalAgeRange' => $usia($p['age'] ?? null),
        'provider' => ['@id' => $orgId],
        'offers' => array_filter([
            '@type' => 'Offer',
            'price' => $harga($p['price'] ?? null),
            'priceCurrency' => 'IDR',
            'category' => 'Paid',
            'availability' => 'https://schema.org/InStock',
            'url' => route('public.programs').'#'.($p['slug'] ?? ''),
        ]),
        'hasCourseInstance' => array_filter([
            '@type' => 'CourseInstance',
            // Semua kelas berlangsung tatap muka di studio.
            'courseMode' => 'Onsite',
            'courseWorkload' => $durasi($p['duration'] ?? null),
            'location' => ['@id' => $orgId],
        ]),
    ]))->values();

    $jsonLdPrograms = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'Program & kelas '.config('site.name'),
        'itemListElement' => $courses->map(fn (array $course, int $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'item' => $course,
        ])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
@endphp

<script type="application/ld+json">{!! $jsonLdPrograms !!}</script>
