{{--
    FAQ halaman kontak sebagai data terstruktur, supaya pertanyaan-jawabannya
    berpeluang tampil langsung di hasil pencarian. Sumbernya config('site.faq'),
    sama persis dengan yang dirender jadi accordion di layar — Google menolak
    FAQPage yang isinya tidak terlihat pengunjung.
--}}
@php
    $jsonLdFaq = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($faq)->map(fn (array $item) => [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
        ])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
@endphp

<script type="application/ld+json">{!! $jsonLdFaq !!}</script>
