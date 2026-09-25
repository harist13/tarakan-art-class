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
    'description' => 'Ruang belajar seni dari dasar di Tarakan: berkreativitas, eksplorasi ide, '
        .'dan berkarya tanpa takut salah. Kelas kecil dengan tutor yang sabar.',

    // ─── Kontak ────────────────────────────────────────────────────────
    'contact' => [
        // Format internasional tanpa "+" — dipakai untuk tautan wa.me
        'whatsapp' => env('SITE_WHATSAPP', '6285217423987'),
        'whatsapp_display' => env('SITE_WHATSAPP_DISPLAY', '+62 852-1742-3987'),
        'email' => env('SITE_EMAIL', 'halo@tarakanartclass.com'),
        'instagram' => env('SITE_INSTAGRAM', 'tarakanartclass'),
        'address' => env('SITE_ADDRESS', 'Jalan Gajah Mada No. 31, Tarakan, Kalimantan Utara'),
        // URL embed peta studio. Ambil dari Google Maps → Bagikan → Sematkan peta,
        // lalu salin isi atribut src milik iframe-nya (bukan tautan biasa).
        // ?: dipakai karena SITE_MAPS_EMBED= (kosong di .env) dibaca sebagai string
        // kosong, bukan null, sehingga argumen default env() tidak akan terpakai.
        'maps_embed' => env('SITE_MAPS_EMBED') ?: 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d828.2972771249796!2d117.57755015475615!3d3.3064980673986435!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x32138bcc85f4672d%3A0x7d07429db2e8ef8b!2sTarakan%20Art%20Class!5e0!3m2!1sid!2sid!4v1786198529973!5m2!1sid!2sid',
    ],

    // Ke mana notifikasi lead dikirim. Kosong = pakai contact.email.
    'lead_notification_email' => env('SITE_LEAD_EMAIL'),

    'hours' => [
        ['day' => 'Senin – Sabtu', 'time' => '09.00 – 12.00, 13.00 – 18.00 WITA'],
        ['day' => 'Minggu', 'time' => 'Tutup'],
    ],

    // Jam operasional yang sama, tapi dalam bentuk yang bisa dibaca mesin —
    // dipakai untuk openingHoursSpecification di data terstruktur (schema.org).
    // Dipisah dari 'hours' di atas karena yang itu ditulis untuk dibaca manusia
    // dan formatnya bebas berubah tanpa merusak apa pun.
    'hours_schema' => [
        // Istirahat 12.00–13.00, jadi satu hari ditulis sebagai dua rentang.
        ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'opens' => '09:00', 'closes' => '12:00'],
        ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'opens' => '13:00', 'closes' => '18:00'],
    ],

    // ─── SEO ───────────────────────────────────────────────────────────
    // Bahan untuk data terstruktur & verifikasi mesin pencari. Alamat dipecah
    // per bagian di sini karena schema.org menuntut komponen terpisah,
    // sedangkan contact.address di atas satu baris utuh untuk ditampilkan.
    'seo' => [
        // Isi dengan kode dari Google Search Console → Verifikasi → Tag HTML
        // (hanya bagian content="..."-nya). Kosong = tag tidak dicetak.
        'google_verification' => env('SITE_GOOGLE_VERIFICATION'),

        'address' => [
            'street' => env('SITE_ADDRESS_STREET', 'Jalan Gajah Mada No. 31'),
            'locality' => env('SITE_ADDRESS_CITY', 'Tarakan'),
            'region' => env('SITE_ADDRESS_REGION', 'Kalimantan Utara'),
            'postal_code' => env('SITE_ADDRESS_POSTAL'),
            'country' => 'ID',
        ],

        // Koordinat studio. Nilai bawaan diambil dari URL embed peta di atas
        // (!3d = lintang, !2d = bujur). Perbarui bila alamat studio pindah.
        'geo' => [
            'lat' => env('SITE_GEO_LAT', '3.3064980'),
            'lng' => env('SITE_GEO_LNG', '117.5775501'),
        ],

        // Rentang harga termurah–termahal dari daftar 'programs' di bawah.
        'price_range' => 'Rp150.000 – Rp300.000',

        // Kota/wilayah yang dilayani — dipakai sebagai areaServed.
        'area_served' => ['Tarakan', 'Kalimantan Utara'],
    ],

    // ─── Program & Kelas ───────────────────────────────────────────────
    // Empat program tetap yang diiklankan di website & ditawarkan di form
    // pendaftaran. Setiap program bisa diikuti Reguler (bulanan) atau Visit
    // (sekali datang).
    //
    // `categories` = nilai `classes.class_category` yang dihitung sebagai program
    // itu (dicocokkan tanpa peduli besar-kecil huruf & tanda baca, jadi
    // "Pre-school" = "preschool"). Durasi, kapasitas, biaya, dan jadwal kartu
    // ditarik live dari slot-slot kategori tersebut; angka di bawah hanya
    // cadangan selama belum ada slot yang dibuka. Kategori yang tidak disebut di
    // mana pun (mis. ABK) tidak tampil di website.
    //
    // Holiday Class tidak punya baris di tabel `classes` — datanya dari modul
    // Holiday Class.
    'programs' => [
        [
            'slug' => 'preschool',
            'name' => 'Preschool',
            'categories' => ['Preschool', 'Pre-school'],
            'age' => '2,5 – 3 tahun',
            'duration' => '60 menit / pertemuan',
            'capacity' => '6 anak per kelas',
            'price' => 'Rp250.000 / bulan',
            'visit_price' => 'Rp115.000 / visit',
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
            'slug' => 'sketching',
            'name' => 'Sketching',
            'form_label' => 'Sketching / Sketsa',
            'categories' => ['Basic Sketch','Basic Perspective', 'Character', 'Drawing'],
            'age' => '7 tahun ke atas',
            'duration' => '90 menit / pertemuan',
            'capacity' => '8 anak per kelas',
            'price' => 'Rp300.000 / bulan',
            'visit_price' => 'Rp105.000 / visit',
            'schedule_hint' => 'Jumat & Sabtu, 16.00 WITA',
            // Teks tetap yang menang atas angka hasil rangkuman slot di database.
            'capacity_label' => '1 tutor max 3–4 anak',
            'schedule_label' => 'Senin – Sabtu',
            'color' => 'sky',
            'icon' => 'pencil',
            'summary' => 'Anak belajar menggambar dari dasar: cara memegang pensil, menarik garis, '
                .'bentuk-bentuk dasar, dan fundamental art lainnya. sketsa & proporsi
merancang karakter sendiri
perspektif',
            'highlights' => [
                'Sketsa & proporsi',
                'Merancang karakter sendiri',
                'Perspektif',
            ],
        ],
        [
            'slug' => 'coloring',
            'name' => 'Coloring',
            'form_label' => 'Coloring / Mewarnai',
            'categories' => ['Coloring', 'Basic Mewarnai', 'Mewarnai'],
            'age' => '4 tahun ke atas',
            'duration' => '75 menit / pertemuan',
            'capacity' => '8 anak per kelas',
            'price' => 'Rp275.000 / bulan',
            'visit_price' => 'Rp105.000 / visit',
            'schedule_hint' => 'Rabu & Sabtu, 14.00 WITA',
            'capacity_label' => '1 tutor max 3–4 anak',
            'schedule_label' => 'Senin – Sabtu',
            'color' => 'coral',
            'icon' => 'palette',
            'summary' => 'Belajar mewarnai dengan krayon dari dasar. '
                .'Anak belajar berbagai teknik mewarnai dengan menggunakan krayon.',
            'highlights' => [
                'Gradasi & pencampuran warna',
                'Crayon, pensil warna, cat air',
                'Persiapan lomba mewarnai',
            ],
        ],
        [
            'slug' => 'holiday',
            // Data live-nya dari modul Holiday Class. Nilai di bawah cuma cadangan
            // saat belum ada sesi yang dijadwalkan admin — lihat
            // PublicSiteController::withHolidaySession().
            //
            // Tidak ada pilihan Reguler/Visit dan tidak ikut form pendaftaran:
            // harganya berbeda tiap sesi, jadi kartunya langsung mengarahkan ke
            // chat WhatsApp admin.
            'source' => 'holiday_classes',
            'name' => 'Holiday Class',
            'categories' => [],
            'age' => '4 – 12 tahun',
            'duration' => '2 jam / sesi',
            'capacity' => '12 anak per sesi',
            'price' => null,
            'schedule_hint' => 'Musiman — libur sekolah',
            'color' => 'leaf',
            'icon' => 'sun',
            'summary' => 'Kelas singkat saat libur sekolah dengan tema berbeda tiap sesi: '
                .'melukis tote bag, clay, mural mini, dan lainnya.',
            'highlights' => [
                'Tema berganti tiap sesi',
                'Info harga & jadwal langsung dari admin',
                'Semua bahan disediakan',
            ],
        ],
    ],

    // ─── Tentang ───────────────────────────────────────────────────────
    'about' => [
        // Sama dengan deskripsi hero di beranda.
        'story' => 'Tarakan Art Class hadir sebagai ruang buat kamu belajar seni dari dasar. '
            .'Memberi kamu ruang untuk berkreativitas, eksplorasi ide, dan tentunya '
            .'berkarya tanpa takut salah!',
        'vision' => 'Menjadi ruang belajar seni yang menyenangkan, kreatif, dan inspiratif, tempat setiap anak '
            .'dapat mengembangkan potensi, menemukan cara berekspresi, dan berani berkarya.',
        'mission' => [
            'Menciptakan suasana belajar seni yang fun, nyaman, dan positif agar anak menikmati proses berkarya.',
            'Fokus menguatkan teknik dasar anak.',
            'Mengajarkan dasar dan teknik seni sesuai dengan kemampuan dan perkembangan setiap anak.',
            'Mendorong anak untuk bereksplorasi dan bereksperimen dengan berbagai ide, media, teknik, dan gaya.',
            'Memberikan ruang bagi anak untuk mengekspresikan diri melalui karya seni tanpa takut salah.',
            'Menghargai setiap proses dan perkembangan, bukan hanya berfokus pada hasil akhir.',
            'Menumbuhkan keberanian, kreativitas, dan rasa percaya diri melalui pengalaman berkarya.',
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
            ['value' => '6', 'label' => 'Tahun berjalan'],
            ['value' => '8', 'label' => 'Maks. anak per kelas (semi private class)'],
        ],
    ],

    // ─── Testimoni ─────────────────────────────────────────────────────
    'testimonials' => [
        [
            'name' => 'Bu Rina',
            'role' => 'Orang tua murid Coloring',
            'quote' => 'Anak saya yang tadinya malu-malu sekarang minta sendiri berangkat kelas. '
                .'Hasil gambarnya juga jauh lebih rapi dari sebelumnya.',
        ],
        [
            'name' => 'Pak Hendra',
            'role' => 'Orang tua murid Sketching',
            'quote' => 'Tutornya sabar dan komunikatif. Tiap semester kami dapat raport, '
                .'jadi tahu persis perkembangan anak.',
        ],
        [
            'name' => 'Bu Sari',
            'role' => 'Orang tua murid Preschool',
            'quote' => 'Studionya bersih dan aman untuk anak kecil. Kelasnya kecil, '
                .'jadi anak saya benar-benar didampingi.',
        ],
    ],

    // ─── Galeri ────────────────────────────────────────────────────────
    // Taruh file di public/images/gallery/, lalu daftarkan di sini.
    // `category` harus salah satu slug program di atas (atau 'kegiatan').
    'gallery' => [
        // ['file' => 'karya-01.webp', 'category' => 'coloring'],
    ],

    'gallery_categories' => [
        'preschool' => 'Preschool',
        'coloring' => 'Coloring',
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
            // Satu baris per kelas; baris baru ("\n") ikut tampil di accordion.
            'a' => "Kelas Preschool: tidak perlu membawa apa-apa.\n"
                ."Kelas Mewarnai: setiap murid perlu menyiapkan krayon (rekomendasi: Faber-Castell minimal 48 warna).\n"
                .'Kelas Sketching: tidak perlu membawa apa-apa selama masih di materi Basic Sketch.',
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
