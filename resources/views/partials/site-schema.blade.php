{{--
    Data terstruktur (JSON-LD schema.org) untuk seluruh website publik.

    Gunanya dua: memberi Google keterangan yang tidak bisa ditebak dari teks
    biasa (jenis usaha, alamat, koordinat, jam buka), dan membuka pintu ke
    tampilan hasil pencarian yang lebih kaya. Semua bahannya dari config/site.php
    supaya isinya tidak pernah berbeda dengan yang tampil di layar.

    Ditulis sebagai satu @graph, bukan beberapa <script> terpisah, supaya
    entitasnya bisa saling menunjuk lewat @id — Google membaca relasi itu.

    Halaman tertentu menambah blok sendiri (Course di /program, FAQPage di
    /kontak) lewat @push('head') di Blade masing-masing.
--}}
@php
    $site = config('site');
    $home = route('public.home');
    $orgId = $home.'#organization';
    $siteId = $home.'#website';

    $organization = array_filter([
        '@type' => ['EducationalOrganization', 'LocalBusiness'],
        '@id' => $orgId,
        'name' => $site['name'],
        'description' => $site['description'],
        'url' => $home,
        'logo' => asset('images/logo-192.png'),
        'image' => asset('images/logo-192.png'),
        'telephone' => '+'.$site['contact']['whatsapp'],
        'email' => $site['contact']['email'],
        'priceRange' => $site['seo']['price_range'] ?? null,
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $site['seo']['address']['street'] ?? null,
            'addressLocality' => $site['seo']['address']['locality'] ?? null,
            'addressRegion' => $site['seo']['address']['region'] ?? null,
            'postalCode' => $site['seo']['address']['postal_code'] ?? null,
            'addressCountry' => $site['seo']['address']['country'] ?? null,
        ]),
        'geo' => array_filter([
            '@type' => 'GeoCoordinates',
            'latitude' => $site['seo']['geo']['lat'] ?? null,
            'longitude' => $site['seo']['geo']['lng'] ?? null,
        ]),
        'areaServed' => $site['seo']['area_served'] ?? null,
        'sameAs' => array_values(array_filter([
            $site['contact']['instagram'] ? 'https://www.instagram.com/'.$site['contact']['instagram'] : null,
        ])),
        'openingHoursSpecification' => array_map(fn (array $slot) => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => $slot['days'],
            'opens' => $slot['opens'],
            'closes' => $slot['closes'],
        ], $site['hours_schema'] ?? []),
    ]);

    $website = [
        '@type' => 'WebSite',
        '@id' => $siteId,
        'url' => $home,
        'name' => $site['name'],
        'inLanguage' => 'id-ID',
        'publisher' => ['@id' => $orgId],
    ];

    $webpage = array_filter([
        '@type' => 'WebPage',
        '@id' => url()->current().'#webpage',
        'url' => url()->current(),
        'name' => $metaTitle ?? $site['name'],
        'description' => $metaDescription ?? $site['description'],
        'inLanguage' => 'id-ID',
        'isPartOf' => ['@id' => $siteId],
        'about' => ['@id' => $orgId],
    ]);

    $graph = [$organization, $website, $webpage];

    // Remah jejak hanya masuk akal di halaman selain beranda: dua tingkat,
    // Beranda → halaman ini. Judul ruasnya memakai judul halaman tanpa embel
    // nama studio, itulah yang dipakai $pageTitle.
    if (! empty($pageTitle) && url()->current() !== $home) {
        $graph[] = [
            '@type' => 'BreadcrumbList',
            '@id' => url()->current().'#breadcrumb',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $pageTitle, 'item' => url()->current()],
            ],
        ];
    }

    $jsonLd = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
    );
@endphp

<script type="application/ld+json">{!! $jsonLd !!}</script>
