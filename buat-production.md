# Rencana Go-Live Production — Tarakan Art Class

> Dokumen persiapan pemindahan sistem dari lingkungan pengembangan (Laragon + tunnel ngrok,
> Midtrans Sandbox) ke server production dengan domain asli dan Midtrans Production.
> Langkah servernya ditulis untuk **Hostinger** (Bagian 5).
>
> **Disusun:** 20 Agustus 2026, berdasarkan pemeriksaan langsung terhadap kode, `.env`,
> dan uji webhook ke endpoint yang sedang berjalan.

---

## 1. Ringkasan

| Aspek | Keterangan |
|---|---|
| Perubahan kode **wajib** | Tidak ada |
| Perubahan kode **disarankan** | 1 baris — `trustProxies` (lihat Bagian 4) |
| Perubahan utama | `.env`, dashboard Midtrans Production, cron server |
| Risiko terbesar | `APP_DEBUG=true` terbawa ke production; Notification URL Production kosong |
| Status webhook | ✅ Terbukti end-to-end di Sandbox — 3 pembayaran DANA sungguhan (lihat Bagian 2) |

**Temuan penting:** tidak ada satu pun cabang kode yang bergantung pada `APP_ENV` atau
`app()->isLocal()`, dan tidak ada URL ngrok/localhost yang tertanam di kode aplikasi.
Seluruh perbedaan sandbox↔production hidup di `.env`. Ini yang membuat migrasinya ringan.

---

## 2. Status Sekarang (sudah terverifikasi)

Hasil pemeriksaan 20 Agustus 2026 — jangan diuji ulang tanpa alasan:

| Komponen | Status | Bukti |
|---|---|---|
| Route webhook `/midtrans/notification` | ✅ Hidup | `routes/web.php:43` |
| Pengecualian CSRF | ✅ Bekerja | POST uji menjawab `403 Invalid signature`, bukan `419` |
| Verifikasi signature SHA-512 | ✅ Bekerja | Signature palsu ditolak, signature sah diterima |
| Order tak dikenal dijawab `200` | ✅ Benar | Tombol "Tes URL notifikasi" dashboard tidak akan gagal |
| Notification URL di dashboard **Sandbox** | ✅ Terpasang | Menunjuk ke domain ngrok yang sama dengan `APP_URL` |
| Notifikasi Midtrans **asli** pernah masuk | ✅ Sudah | 3 pembayaran DANA sungguhan, tabel di bawah |

### Bukti pembayaran e-wallet sungguhan (Sandbox)

Riwayat notifikasi di Dashboard Midtrans dicocokkan dengan isi tabel `payments`:

| Invoice | `order_id` | Status | Channel | `paid_at` (UTC) | Notifikasi dikirim (WIB) |
|---|---|---|---|---|---|
| INV018 | `INV018-107-1` | `paid` / `settlement` | dana | 18 Agt 15:42:15 | 18 Agt 22:42 |
| INV019 | `INV019-108-1` | `paid` / `settlement` | dana | 18 Agt 16:30:28 | 18 Agt 23:30 |
| INV021 | `INV021-110-1` | `paid` / `settlement` | dana | 19 Agt 06:35:53 | 19 Agt 13:35 |

Waktu pengiriman notifikasi dan waktu invoice menjadi lunas cocok sampai ke menitnya — selisih
7 jam murni karena dashboard memakai WIB sedangkan database menyimpan UTC. Ini jejak sebab-akibat,
bukan kebetulan: notifikasi masuk → invoice lunas. Ketiganya **DANA**, yaitu channel yang paling
rapuh (tidak bisa diselamatkan tombol *Cek Status*) — jadi justru bagian tersulit yang terbukti.

> ⚠️ **Jangan menilai kesehatan webhook dari `storage/logs/laravel.log` saja.** Berkas log
> sempat dibersihkan, sehingga ketiga pembayaran di atas tidak meninggalkan jejak di log yang ada
> sekarang — log yang sunyi sempat disalahartikan sebagai "notifikasi tidak pernah datang".
> Sumber kebenaran yang tahan bersih-bersih adalah kolom `gateway_status`, `gateway_payment_type`,
> dan `paid_at` di tabel `payments`, dicocokkan dengan riwayat notifikasi di Dashboard Midtrans.

---

## 3. Perubahan `.env`

Satu-satunya berkas yang wajib disesuaikan. Tabel ini juga menjelaskan akibat kalau terlewat.

### Kritis

| Baris | Sekarang | Production | Kalau lupa |
|---|---|---|---|
| `APP_DEBUG` | `true` | **`false`** | Halaman error memamerkan isi `.env` — termasuk server key Midtrans — ke publik |
| `APP_ENV` | `local` | `production` | Pengaman `migrate` hilang, perilaku cache berbeda |
| `APP_URL` | domain ngrok | domain asli (dengan `https://`) | Tautan `/bayar/{token}` di WhatsApp menunjuk ke alamat mati |
| `MIDTRANS_SERVER_KEY` | sandbox | kunci Production | Signature webhook tak pernah cocok |
| `MIDTRANS_CLIENT_KEY` | sandbox | kunci Production | Snap gagal dimuat di halaman bayar |
| `MIDTRANS_IS_PRODUCTION` | `false` | `true` | Uang asli ditembak ke endpoint sandbox |

> **Kunci Midtrans tidak bisa ditebak dari awalannya.** Jangan mengandalkan ada/tidaknya
> awalan `SB-` untuk memastikan sebuah kunci milik sandbox atau production — ambil langsung
> dari tab yang benar di Dashboard → Settings → Access Keys.

### Penting

| Baris | Sekarang | Production | Kalau lupa |
|---|---|---|---|
| `DB_*` | root tanpa sandi | kredensial server | Aplikasi tidak jalan (gagal keras, mudah terdeteksi) |
| `MAIL_MAILER` | `log` | SMTP asli | Lead dari form Kontak hanya masuk berkas log |
| `SITE_LEAD_EMAIL` | kosong | email yang **benar-benar dipantau** | Lead masuk tanpa ada yang tahu — belum ada halaman admin untuk lead |
| `LOG_LEVEL` | `debug` | `warning` | Log membengkak, jejak penting tenggelam |
| `SESSION_ENCRYPT` | `false` | `true` (disarankan) | Isi sesi tersimpan polos di database |

### Data website publik

`SITE_WHATSAPP`, `SITE_WHATSAPP_DISPLAY`, `SITE_EMAIL`, `SITE_INSTAGRAM`, `SITE_ADDRESS`,
`SITE_MAPS_EMBED` — sekarang masih berisi contoh (`6281234567890`, alamat dummy). Isi dengan
data asli sebelum dibuka ke publik. Deskripsi program, harga, dan jam operasional diambil dari
`config/site.php`, bukan database — mengubahnya berarti mengubah berkas itu lalu deploy ulang.

---

## 4. Perubahan Kode yang Disarankan

### `bootstrap/app.php:17` — `trustProxies(at: '*')`

Baris ini ditambahkan khusus untuk ngrok: tanpa itu `url()` menghasilkan `http://` padahal
diakses lewat `https://`. Tapi `'*'` berarti "percayai header `X-Forwarded-*` dari siapa pun".
Di server production yang terbuka ke internet, siapa pun bisa memalsukan IP asalnya lewat
`X-Forwarded-For` — cukup untuk membelokkan rate limiter `throttle:15,1` di route verifikasi
pembayaran dan mengotori log.

Perbaikannya tergantung bentuk server — **putuskan setelah hosting dipilih**:

| Topologi | Ganti menjadi |
|---|---|
| Nginx/Apache di mesin yang sama | `at: ['127.0.0.1', '::1']` |
| Di belakang Cloudflare / load balancer | Daftar rentang IP milik penyedia tersebut |
| Langsung ke PHP tanpa proxy | Hapus baris `trustProxies` sama sekali |

**Untuk Hostinger:** shared hosting maupun VPS-nya menjalankan web server (LiteSpeed/Apache) di
mesin yang sama dengan PHP, jadi jawabannya baris pertama — `at: ['127.0.0.1', '::1']`. Kalau
nanti domain dipasang di belakang Cloudflare, barulah rentang IP Cloudflare perlu ditambahkan.

Ini satu-satunya perubahan kode dalam rencana ini. Sifatnya pengerasan keamanan, bukan
perbaikan bug — sistem tetap berfungsi tanpa itu.

---

## 5. Setup Server di Hostinger — Langkah demi Langkah

### 5.0 Yang dibutuhkan proyek ini

Dibaca dari `composer.json` & `package.json`, bukan tebakan:

| Kebutuhan | Versi / catatan |
|---|---|
| PHP | **^8.3** (Laravel 13) |
| Ekstensi PHP | `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`/`dom`, `ctype`, `json`, `curl`, `fileinfo`, `bcmath`, **`gd`** (dompdf untuk cetak rapor/invoice), `zip` |
| Database | MySQL / MariaDB |
| Composer | v2 |
| Node.js | **hanya untuk build aset** — tidak perlu ada di server (lihat 5.1) |
| Akses SSH | **wajib.** Tanpa SSH, `artisan migrate` & `config:cache` tidak bisa dijalankan |
| Ekstensi lain | Tidak ada Redis/queue worker — `QUEUE_CONNECTION=database` dan tidak ada job yang di-dispatch, jadi tidak perlu proses worker |

> **Syarat paket Hostinger:** pilih paket yang menyediakan **SSH access** (Business ke atas pada
> shared hosting, atau VPS). Paket tanpa SSH secara praktis tidak bisa menjalankan Laravel ini.
> Kalau anggaran memungkinkan, **VPS lebih tenang** — Anda bebas mengatur document root, versi PHP
> CLI, dan cron per menit tanpa akal-akalan.

### 5.1 Build aset di komputer lokal DULU

Hostinger shared hosting tidak menyediakan Node.js, sedangkan proyek ini memakai Vite 8 +
Tailwind 4. Jadi aset **dibangun di laptop**, lalu hasilnya diunggah:

```
npm ci
npm run build
```

Hasilnya ada di `public/build/`. Folder itu biasanya masuk `.gitignore`, jadi kalau deploy lewat
git ia **tidak ikut terkirim** — harus diunggah manual lewat File Manager/SFTP. Gejala kalau
terlewat: halaman tampil tanpa CSS sama sekali, atau error "Vite manifest not found".

### 5.2 Domain, DNS, dan SSL (hPanel)

1. Arahkan domain ke Hostinger (nameserver atau A record).
2. hPanel → **Websites → Dashboard → Security → SSL** → pasang sertifikat gratis.
3. Aktifkan **Force HTTPS**.

Wajib HTTPS: Midtrans hanya mengirim notifikasi ke alamat publik yang sah, dan Snap menolak
dimuat di halaman non-HTTPS.

### 5.3 Versi PHP & ekstensi (hPanel)

hPanel → **Advanced → PHP Configuration**:

1. Tab **PHP version** → pilih **8.3** (atau lebih baru yang tersedia).
2. Tab **PHP extensions** → pastikan ekstensi di tabel 5.0 tercentang, terutama **`gd`**
   (tanpa itu cetak PDF rapor/invoice gagal) dan **`zip`** (dibutuhkan Composer).
3. Tab **PHP options** → `upload_max_filesize` & `post_max_size` minimal **8M** (upload foto rapor),
   `memory_limit` minimal **256M**.

> ⚠️ Versi PHP untuk **web** dan untuk **CLI (SSH)** bisa berbeda di Hostinger. Setelah masuk SSH,
> cek `php -v`. Kalau bukan 8.3, panggil versinya secara eksplisit — mis. `/usr/bin/php8.3 artisan …`
> — atau ikuti panduan Hostinger untuk mengubah PHP CLI. Menjalankan `artisan` dengan PHP versi
> lama menghasilkan error yang membingungkan dan tidak ada hubungannya dengan kode.

### 5.4 Database MySQL (hPanel)

hPanel → **Databases → Management**:

1. Buat database + user + sandi kuat. Catat ketiganya.
2. Nama database dan user di Hostinger otomatis diberi awalan (mis. `u123456789_artclass`) —
   salin apa adanya ke `.env`.
3. Isi `.env`: `DB_HOST=localhost`, `DB_PORT=3306`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

### 5.5 Unggah kode & struktur folder

Ini bagian yang paling sering salah di shared hosting. **Isi folder `public/` Laravel harus jadi
document root — sisa proyeknya tidak boleh bisa diakses dari internet.** Kalau seluruh proyek
ditaruh di `public_html`, maka `.env` (berisi server key Midtrans) bisa diunduh siapa saja.

**Opsi A — ubah document root (dipakai bila paket Anda mengizinkan):**

```
~/domains/namadomain.com/
├── app/            ← seluruh proyek Laravel di sini
└── public_html/    ← document root diarahkan ke ../app/public
```

**Opsi B — pisah manual (selalu bisa, dipakai bila document root tidak bisa diubah):**

1. Unggah seluruh proyek ke `~/laravel` (**di luar** `public_html`).
2. Pindahkan **isi** `~/laravel/public/*` ke `~/public_html/`.
3. Sunting `~/public_html/index.php`, ubah dua baris path-nya:

   ```php
   require __DIR__.'/../laravel/vendor/autoload.php';
   $app = require_once __DIR__.'/../laravel/bootstrap/app.php';
   ```

Apa pun opsinya, pastikan `.env`, `storage/`, `vendor/`, dan `app/` **tidak** berada di dalam
`public_html`. Uji dengan membuka `https://domain-anda.com/.env` — harus 404/403, bukan mengunduh berkas.

### 5.6 Composer install (lewat SSH)

hPanel → **Advanced → SSH Access** untuk mengambil host, port, dan user.

```
cd ~/domains/namadomain.com/app     # sesuaikan dengan struktur yang dipilih
composer install --no-dev --optimize-autoloader
```

Kalau prosesnya terbunuh di tengah jalan (umum di shared hosting karena batas memori):

```
php -d memory_limit=-1 /usr/local/bin/composer install --no-dev --optimize-autoloader
```

Alternatif paling aman: jalankan `composer install --no-dev` di laptop, lalu unggah folder
`vendor/` apa adanya.

### 5.7 `.env`, kunci aplikasi, migrasi

```
cp .env.example .env      # lalu isi sesuai Bagian 3
php artisan key:generate
php artisan migrate --force
```

Jalankan `php artisan db:seed` **hanya** bila ini instalasi baru (untuk membuat peran & akun awal).

### 5.8 Izin folder & symlink storage

```
chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

`storage:link` wajib — foto rapor diunggah ke `storage/app/public/report-photos` dan hanya bisa
tampil lewat symlink ini. Pada **Opsi B**, symlink bawaan menunjuk ke `public/storage` yang sudah
tidak dipakai; buat symlink-nya di `public_html`:

```
ln -s ~/laravel/storage/app/public ~/public_html/storage
```

### 5.9 Bangun cache

```
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> ⚠️ **Jebakan klasik:** setelah `config:cache`, perubahan `.env` **tidak berpengaruh** sampai
> `php artisan config:cache` dijalankan ulang. Banyak orang mengira kunci production tidak
> terpasang padahal `.env`-nya sudah benar — yang salah adalah cache-nya.

### 5.10 Cron (hPanel → Advanced → Cron Jobs)

Cara standar Laravel, jalankan tiap menit:

```
cd ~/domains/namadomain.com/app && php artisan schedule:run >> /dev/null 2>&1
```

**Kalau paket Anda membatasi interval cron** (beberapa paket shared tidak mengizinkan tiap menit),
proyek ini punya jalan pintas yang aman: satu-satunya pekerjaan terjadwal adalah penangguhan murid
pukul 06:00, jadi panggil perintahnya langsung sekali sehari:

```
cd ~/domains/namadomain.com/app && php artisan students:suspend-overdue >> /dev/null 2>&1
```

Konsekuensinya: setiap penjadwalan baru yang ditambahkan ke `routes/console.php` di kemudian hari
**tidak akan jalan** sampai cron-nya diperbaiki. Catat ini kalau memilih jalan pintas tersebut.

### 5.11 Email (untuk lead dari form Kontak)

Buat kotak surat di hPanel → **Emails**, lalu isi `.env` dengan SMTP-nya
(`MAIL_MAILER=smtp`, host & port dari panduan Hostinger, `MAIL_USERNAME` = alamat email penuh).
Isi juga `SITE_LEAD_EMAIL` dan `MAIL_FROM_ADDRESS` dengan alamat di domain sendiri — email yang
dikirim dari domain asing lebih sering masuk folder spam.

### 5.12 Checklist singkat urutan kerja

- [ ] Paket Hostinger dengan SSH aktif
- [ ] `npm run build` di laptop, `public/build/` ikut terunggah
- [ ] Domain + SSL + Force HTTPS
- [ ] PHP 8.3 + ekstensi (`gd`, `zip`, dll.)
- [ ] Database dibuat, kredensial dicatat
- [ ] Kode terunggah, document root menunjuk ke `public/`
- [ ] `https://domain/.env` mengembalikan 404/403
- [ ] `composer install --no-dev -o`
- [ ] `.env` diisi (Bagian 3) → `key:generate` → `migrate --force`
- [ ] `chmod` + `storage:link`
- [ ] `config:cache route:cache view:cache`
- [ ] Cron terpasang
- [ ] Lanjut ke Bagian 6 (Notification URL Midtrans) lalu Bagian 7 (verifikasi)

---

## 6. Pekerjaan di Luar Kode

### 6.1 Notification URL Midtrans — dashboard Production

Sandbox dan Production adalah **dua dashboard terpisah dengan setelan terpisah**. Kolom
Notification URL di dashboard Production kosong sampai diisi sendiri.

- Isi dengan: `https://{domain-asli}/midtrans/notification`
- Menu: Dashboard Midtrans → Settings → Configuration → Payment Notification URL

Ini yang dimaksud poin "Notifikasi Midtrans wajib dipasang" di dokumen peta sistem. Kode
webhooknya sudah benar sejak awal — yang bisa bikin celaka adalah kolom dashboard yang kosong.

### 6.2 Cron — tanpa ini, penangguhan murid mati

`routes/console.php` menjadwalkan `students:suspend-overdue` tiap pukul 06:00, tapi penjadwal
Laravel butuh satu cron di server. Cara memasangnya di hPanel ada di **Bagian 5.10**, termasuk
jalan pintas bila paket Anda tidak mengizinkan cron tiap menit.

Tanpa ini tunggakan berhenti di tingkat dua dan murid tidak pernah benar-benar tertangguhkan.

### 6.3 Sandi bawaan

`database/seeders/RolePermissionSeeder.php` membuat dua akun dengan sandi `password`.
**Ganti keduanya lewat menu Pengguna setelah deploy** — jangan diubah di seeder, karena
seeder tidak dijalankan ulang di server.

### 6.4 Cadangan (backup)

Siapkan backup harian database sebelum sistem menerima uang sungguhan. Data pembayaran
adalah satu-satunya data yang tidak bisa direkonstruksi ulang dari mana pun.

---

## 7. Verifikasi Setelah Deploy

Jangan anggap selesai sebelum ketiga langkah ini lulus.

### 7.1 Dasar

- [ ] Buka halaman utama — tampil dengan aset (CSS/JS) yang benar
- [ ] Login sebagai admin
- [ ] Picu error kecil (mis. URL ngawur) — pastikan **tidak** muncul halaman debug Laravel

### 7.2 Webhook dapat dijangkau

- [ ] Dashboard Midtrans Production → tombol **Tes URL notifikasi** → harus `200`
- [ ] Cek `storage/logs/laravel.log` — harus ada baris `Notifikasi Midtrans diterima`

### 7.3 Pembayaran sungguhan (paling penting)

Alur ini **sudah terbukti di Sandbox** (Bagian 2), tapi Production adalah kunci, domain, dan
setelan dashboard yang sama sekali berbeda — bukti sandbox tidak berlaku di sana. Ulangi satu
transaksi nyata bernominal kecil dengan **QRIS/e-wallet** (bukan Virtual Account):

- [ ] Buat invoice, buka tautan `/bayar/{token}`, bayar sampai tuntas
- [ ] Di database: `payment_status=paid`, `gateway_status=settlement`, `paid_at` terisi — dan
      waktunya berdekatan dengan waktu notifikasi di dashboard (ingat beda WIB↔UTC)
- [ ] Di log muncul: `Invoice INVxxx lunas via Midtrans (qris)`
      — **tanpa** embel-embel "diverifikasi dari halaman bayar". Kalau embel-embel itu ada,
      yang melunasi adalah callback peramban, bukan webhook; webhooknya masih belum terbukti.
- [ ] Dashboard Midtrans → Lihat riwayat notifikasi → status kirim `200`
- [ ] Invoice di layar Pembayaran berubah jadi **Lunas**, dan pemasukannya muncul di Laporan Keuangan

> **Kenapa harus e-wallet, bukan Virtual Account:** tombol *Cek Status* menarik data lewat
> `order_id`, dan untuk DANA/GoPay pencarian itu kerap pulang kosong meski transaksinya nyata.
> VA yang webhooknya mati masih bisa ditambal manual; e-wallet tidak — webhook adalah
> satu-satunya jalan. Jadi e-wallet adalah channel yang paling perlu dibuktikan.

---

## 8. Diagnosa Cepat

| Gejala | Kemungkinan penyebab |
|---|---|
| Bayar sukses di sisi orang tua, invoice tetap Unpaid | Notification URL Production kosong / salah; atau `APP_URL` dan dashboard menunjuk alamat berbeda |
| Log berisi "signature tidak cocok" | `MIDTRANS_SERVER_KEY` bukan milik environment yang sama dengan dashboard pengirim |
| Log kosong sama sekali saat ada pembayaran | Notifikasi tidak pernah sampai — periksa DNS, firewall, dan URL di dashboard |
| `.env` sudah benar tapi perilaku lama bertahan | `config:cache` belum dibangun ulang |
| Snap tidak muncul di halaman bayar | `MIDTRANS_CLIENT_KEY` salah environment, atau `MIDTRANS_IS_PRODUCTION` tidak sinkron dengan kuncinya |
| Murid menunggak tapi tidak pernah tertangguhkan | Cron `schedule:run` belum dipasang |

---

## 9. Peningkatan Opsional (setelah go-live stabil)

Bukan syarat go-live, tapi menaikkan ketenangan jangka panjang.

1. **Indikator "notifikasi terakhir diterima"** di layar Pembayaran.
   `MidtransSnap::webhookReachable()` hanya memeriksa apakah `APP_URL` tampak lokal — ia
   **tidak bisa tahu** kalau dashboard Midtrans menunjuk ke alamat lama. Ada satu skenario yang
   lolos semua pengaman: sistem merasa sehat, dashboard salah alamat, dan yang pertama tahu
   adalah orang tua yang marah karena sudah bayar. Menampilkan *"Notifikasi terakhir diterima
   3 hari lalu"* cukup untuk memancing curiga sebelum ada korban.
2. **Halaman admin untuk lead** dari form Kontak — sekarang lead hanya masuk database dan email,
   tanpa antarmuka untuk melihatnya.
3. **Konten website publik ke database** — deskripsi program, harga, dan jam operasional masih
   di `config/site.php`, jadi mengubah harga berarti deploy ulang.

---

## 10. Ringkasan Satu Halaman

**Kode:** nol perubahan wajib; satu pengerasan disarankan (`trustProxies`).

**Wajib sebelum menerima uang sungguhan:**

1. `APP_DEBUG=false` — dan `https://domain/.env` mengembalikan 404/403
2. Kunci Midtrans Production + `MIDTRANS_IS_PRODUCTION=true`
3. Notification URL di dashboard **Production**
4. Cron `schedule:run`
5. Ganti sandi kedua akun seeder
6. Satu transaksi e-wallet nyata di **Production** yang terbukti lunas lewat webhook

Nomor 6 adalah penutupnya — selama itu belum lulus, sisanya baru asumsi. Alur ini sudah lulus
di Sandbox (3 pembayaran DANA), jadi yang diuji ulang di Production bukan lagi benar-tidaknya
kode, melainkan benar-tidaknya kunci, domain, dan setelan dashboard yang baru.
