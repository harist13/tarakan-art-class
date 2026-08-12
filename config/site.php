<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Website Publik — Tarakan Art Class
|--------------------------------------------------------------------------
| Semua konten "statis" website marketing dikumpulkan di sini supaya bisa
| diubah tanpa menyentuh Blade. Data operasional (jadwal, kursi tersisa)
| tetap diambil live dari database sistem admin.
*/

return [

    'name' => 'Tarakan Art Class',
    'tagline' => 'Kelas seni untuk anak di Tarakan',
    'description' => 'Kelas menggambar & mewarnai untuk anak usia pra-sekolah sampai SD di Tarakan. '
        .'Tutor berpengalaman, kelas kecil, suasana ramah anak.',

    // ─── Kontak ────────────────────────────────────────────────────────
    'contact' => [
        // Format internasional tanpa "+" — dipakai untuk tautan wa.me
        'whatsapp' => env('SITE_WHATSAPP', '6281234567890'),
        'whatsapp_display' => env('SITE_WHATSAPP_DISPLAY', '+62 812-3456-7890'),
        'email' => env('SITE_EMAIL', 'halo@tarakanartclass.com'),
        'instagram' => env('SITE_INSTAGRAM', 'tarakanartclass'),
        'address' => env('SITE_ADDRESS', 'Jl. Yos Sudarso No. 12, Tarakan, Kalimantan Utara'),
        // URL embed peta studio. Ambil dari Google Maps → Bagikan → Sematkan peta,
        // lalu salin isi atribut src milik iframe-nya (bukan tautan biasa).
        'maps_embed' => env('SITE_MAPS_EMBED', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d828.2972771249796!2d117.57755015475615!3d3.3064980673986435!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x32138bcc85f4672d%3A0x7d07429db2e8ef8b!2sTarakan%20Art%20Class!5e0!3m2!1sid!2sid!4v1786198529973!5m2!1sid!2sid'),
    ],

    // Ke mana notifikasi lead dikirim. Kosong = pakai contact.email.
    'lead_notification_email' => env('SITE_LEAD_EMAIL'),

    'hours' => [
        ['day' => 'Senin – Jumat', 'time' => '13.00 – 18.00 WITA'],
        ['day' => 'Sabtu', 'time' => '09.00 – 17.00 WITA'],
        ['day' => 'Minggu', 'time' => 'Tutup (kecuali Holiday Class)'],
    ],

    // ─── Program & Kelas ───────────────────────────────────────────────
    // `category` dipetakan ke kolom `classes.class_category` supaya jadwal
    // & sisa kursi bisa ditarik dari database. `null` = kelas musiman.
    'programs' => [
        [
            'slug' => 'preschool',
            'category' => 'preschool',
            'name' => 'Preschool Art',
            'age' => '3 – 5 tahun',
            'duration' => '60 menit / pertemuan',
            'capacity' => '6 anak per kelas',
            'price' => 'Rp250.000 / bulan',
            'schedule_hint' => 'Selasa & Kamis, 15.00 WITA',
            'color' => 'sun',
            'icon' => 'sparkle',
            'summary' => 'Pengenalan warna, bentuk, dan tekstur lewat kegiatan bermain. '
                .'Fokus pada motorik halus dan keberanian berekspresi.',
            'highlights' => [
                'Finger painting & kolase',
                'Mengenal warna primer',
                'Melatih genggaman pensil',
            ],
        ],
        [
            'slug' => 'coloring',
            'category' => 'coloring',
            'name' => 'Coloring Class',
            'age' => '5 – 8 tahun',
            'duration' => '75 menit / pertemuan',
            'capacity' => '8 anak per kelas',
            'price' => 'Rp275.000 / bulan',
            'schedule_hint' => 'Rabu & Sabtu, 14.00 WITA',
            'color' => 'coral',
            'icon' => 'palette',
            'summary' => 'Teknik mewarnai rapi dengan crayon, pensil warna, dan cat air. '
                .'Anak belajar gradasi, komposisi, dan kesabaran.',
            'highlights' => [
                'Gradasi & pencampuran warna',
                'Crayon, pensil warna, cat air',
                'Persiapan lomba mewarnai',
            ],
        ],
        [
            'slug' => 'drawing',
            'category' => 'drawing',
            'name' => 'Drawing Class',
            'age' => '8 – 12 tahun',
            'duration' => '90 menit / pertemuan',
            'capacity' => '8 anak per kelas',
            'price' => 'Rp300.000 / bulan',
            'schedule_hint' => 'Jumat & Sabtu, 16.00 WITA',
            'color' => 'sky',
            'icon' => 'pencil',
            'summary' => 'Dasar menggambar: proporsi, perspektif, dan shading. '
                .'Anak mulai membangun gaya menggambarnya sendiri.',
            'highlights' => [
                'Sketsa & proporsi',
                'Perspektif dasar',
                'Ilustrasi karakter',
            ],
        ],
        [
            'slug' => 'holiday',
            'category' => null,
            // Data live-nya bukan dari tabel `classes` (tidak punya kategori), melainkan
            // dari modul Holiday Class. Nilai di bawah cuma cadangan saat belum ada
            // sesi yang dijadwalkan admin — lihat PublicSiteController::withHolidaySession().
            'source' => 'holiday_classes',
            'name' => 'Holiday Class',
            'age' => '4 – 12 tahun',
            'duration' => '2 jam / sesi',
            'capacity' => '12 anak per sesi',
            'price' => 'Rp150.000 / sesi',
            'schedule_hint' => 'Musiman — libur sekolah',
            'color' => 'leaf',
            'icon' => 'sun',
            'summary' => 'Kelas singkat saat libur sekolah dengan tema berbeda tiap sesi: '
                .'melukis tote bag, clay, mural mini, dan lainnya.',
            'highlights' => [
                'Tema berganti tiap sesi',
                'Tanpa komitmen bulanan',
                'Semua bahan disediakan',
            ],
        ],
    ],

    // ─── Tentang ───────────────────────────────────────────────────────
    'about' => [
        'story' => 'Tarakan Art Class lahir dari satu keresahan sederhana: anak-anak di Tarakan '
            .'punya banyak ide, tapi sedikit ruang untuk menuangkannya. Sejak 2019 kami membuka '
            .'studio kecil yang hangat, tempat anak usia 3 sampai 12 tahun bisa mencoret, mewarnai, '
            .'dan menggambar tanpa takut salah.',
        'vision' => 'Menjadi ruang tumbuh anak-anak Tarakan untuk berani berekspresi lewat seni.',
        'mission' => [
            'Menyediakan kelas seni yang menyenangkan, terstruktur, dan sesuai usia.',
            'Menjaga kelas tetap kecil agar setiap anak mendapat perhatian tutor.',
            'Melibatkan orang tua lewat raport perkembangan tiap semester.',
            'Merayakan karya anak lewat pameran dan kegiatan bersama.',
        ],
        'methods' => [
            [
                'title' => 'Kelas kecil',
                'body' => 'Maksimal 8 anak per kelas supaya tutor bisa mendampingi satu per satu.',
            ],
            [
                'title' => 'Kurikulum bertingkat',
                'body' => 'Materi naik bertahap dari mengenal warna sampai ilustrasi, bukan sekadar mewarnai lembar kerja.',
            ],
            [
                'title' => 'Tanpa "salah menggambar"',
                'body' => 'Tutor mengarahkan teknik, bukan menyeragamkan hasil. Gaya tiap anak dihargai.',
            ],
            [
                'title' => 'Raport perkembangan',
                'body' => 'Orang tua menerima catatan kemajuan anak berikut dokumentasi karyanya.',
            ],
        ],
        'stats' => [
            ['value' => '300+', 'label' => 'Murid & alumni'],
            ['value' => '6', 'label' => 'Tahun berjalan'],
            ['value' => '8', 'label' => 'Maks. anak per kelas'],
            ['value' => '12', 'label' => 'Pameran karya'],
        ],
        // Foto tutor opsional: taruh di public/images/tutors/<file> lalu isi `photo`.
        'tutors' => [
            ['name' => 'Kak Ayu', 'role' => 'Preschool & Coloring', 'bio' => 'Sarjana PAUD, 5 tahun mendampingi kelas anak usia dini.', 'photo' => null],
            ['name' => 'Kak Bima', 'role' => 'Drawing & Ilustrasi', 'bio' => 'Ilustrator lepas, mengajar dasar sketsa dan perspektif.', 'photo' => null],
            ['name' => 'Kak Nadia', 'role' => 'Cat air & Mixed media', 'bio' => 'Alumni seni rupa, senang eksperimen media baru bersama anak.', 'photo' => null],
        ],
    ],

    // ─── Testimoni ─────────────────────────────────────────────────────
    'testimonials' => [
        [
            'name' => 'Bu Rina',
            'role' => 'Orang tua murid Coloring Class',
            'quote' => 'Anak saya yang tadinya malu-malu sekarang minta sendiri berangkat kelas. '
                .'Hasil gambarnya juga jauh lebih rapi dari sebelumnya.',
        ],
        [
            'name' => 'Pak Hendra',
            'role' => 'Orang tua murid Drawing Class',
            'quote' => 'Tutornya sabar dan komunikatif. Tiap semester kami dapat raport, '
                .'jadi tahu persis perkembangan anak.',
        ],
        [
            'name' => 'Bu Sari',
            'role' => 'Orang tua murid Preschool Art',
            'quote' => 'Studionya bersih dan aman untuk anak kecil. Kelasnya kecil, '
                .'jadi anak saya benar-benar didampingi.',
        ],
    ],

    // ─── Galeri ────────────────────────────────────────────────────────
    // Taruh file di public/images/gallery/, lalu daftarkan di sini.
    // `category` harus salah satu slug program di atas (atau 'kegiatan').
    'gallery' => [
        // ['file' => 'karya-01.webp', 'category' => 'coloring', 'caption' => 'Karya Alya, 7 tahun'],
    ],

    'gallery_categories' => [
        'preschool' => 'Preschool Art',
        'coloring' => 'Coloring Class',
        'drawing' => 'Drawing Class',
        'holiday' => 'Holiday Class',
        'kegiatan' => 'Kegiatan & Pameran',
    ],

    // ─── FAQ ───────────────────────────────────────────────────────────
    'faq' => [
        [
            'q' => 'Apakah anak saya harus sudah bisa menggambar?',
            'a' => 'Tidak. Sebagian besar murid baru mulai dari nol. Tutor menyesuaikan materi dengan kemampuan awal anak.',
        ],
        [
            'q' => 'Apakah alat dan bahan disediakan?',
            'a' => 'Ya, semua alat dan bahan dasar sudah termasuk dalam biaya bulanan. Anak cukup datang membawa semangat.',
        ],
        [
            'q' => 'Bagaimana kalau anak berhalangan hadir?',
            'a' => 'Kami menyediakan kelas pengganti (replacement class) pada slot yang masih tersedia. Cukup kabari admin sebelum jadwal.',
        ],
        [
            'q' => 'Apakah bisa coba kelas dulu?',
            'a' => 'Bisa. Hubungi kami lewat WhatsApp untuk menjadwalkan satu sesi percobaan sebelum mendaftar.',
        ],
        [
            'q' => 'Bagaimana cara pembayarannya?',
            'a' => 'Pembayaran bulanan dapat dilakukan via transfer atau tunai di studio, dan dicatat pada sistem kami.',
        ],
    ],

    // ─── Kredit ────────────────────────────────────────────────────────
    'credit' => [
        'label' => '',
        'url' => env('SITE_CREDIT_URL', '#'),
    ],
];
