# Deploy Tarakan Art Class ke Render (Paket Gratis)

> Panduan langkah demi langkah memindahkan aplikasi ini ke Render.com dengan biaya
> Rp0. Ditulis 20 September 2026 berdasarkan pemeriksaan langsung isi repositori.
> Untuk persiapan go-live secara umum (Midtrans production, `.env`, cron),
> lihat `buat-production.md` — dokumen ini hanya membahas sisi Render-nya.

---

## 0. Yang perlu diketahui sebelum mulai

**Render tidak punya runtime PHP.** Daftar Language di dashboard hanya berisi Node,
Python, Ruby, Go, Rust, Elixir, dan Docker. Karena itu aplikasi ini dibungkus
Docker. Di layar "New Web Service", Render menebak **Node** hanya karena melihat
`package.json` — tebakan itu **salah** dan harus diganti ke **Docker**.

Berkas Docker-nya sudah disiapkan di repositori:

| Berkas | Isi |
|---|---|
| `Dockerfile` | nginx + php-fpm 8.3 dalam satu container |
| `docker/nginx.conf.template` | konfigurasi nginx, port diisi Render saat start |
| `docker/php.ini` | limit upload 20 MB, memory 256 MB, opcache |
| `docker/entrypoint.sh` | migrasi, cache, symlink storage, lalu jalankan server |
| `.dockerignore` | menahan `vendor/`, `node_modules/`, `.env` keluar dari image |
| `render.yaml` | blueprint opsional (Bagian 3b) |

**Node tidak dibutuhkan sama sekali.** Satu-satunya berkas yang memakai `@vite`
adalah `resources/views/welcome.blade.php`, dan view itu tidak dirujuk route mana
pun. CSS publik dilayani dari `public/css/site.css` yang ikut di-commit, dan
Bootstrap diambil dari CDN. Jadi tidak ada langkah `npm run build`.

### Batas paket gratis Render — baca ini dulu

| Batas | Akibat nyata untuk aplikasi ini |
|---|---|
| Tidur setelah **15 menit** tanpa request | Kunjungan pertama setelah sepi menunggu ~50 detik. Webhook Midtrans yang datang saat tidur bisa timeout (Midtrans akan mengulang kirim). Diatasi di Bagian 8. |
| **Tidak ada disk permanen** | Semua berkas yang ditulis aplikasi **hilang** setiap container restart atau bangun tidur. Foto karya murid termasuk. Diatasi di Bagian 9. |
| **Tidak ada Shell/SSH** | Tidak bisa menjalankan `php artisan` manual. Diganti variabel `STARTUP_COMMAND` / `SEED_CLASS` (Bagian 7). |
| **Tidak ada database MySQL** | Render hanya menyediakan PostgreSQL, dan yang gratis pun dihapus setelah 30 hari. Kode ini butuh MySQL (Bagian 1). |
| **Cron job berbayar** | `students:suspend-overdue` dijalankan dengan cara lain (Bagian 8). |
| 512 MB RAM, 0.1 CPU, 750 jam/bulan | Cukup untuk satu layanan yang hidup terus-menerus. |

### Kenapa harus MySQL, tidak boleh PostgreSQL

Bukan selera — kodenya memang mengunci MySQL di tiga tempat:

- `app/Models/Artwork.php:51` dan `app/Http/Controllers/ReportController.php:53`
  memakai `DATE_FORMAT(...)`, fungsi yang tidak ada di PostgreSQL. Cabang
  alternatifnya hanya disediakan untuk `sqlite`.
- `app/Http/Controllers/DashboardController.php:154` menulis
  `students.status = "active"` dengan tanda kutip ganda. Di PostgreSQL kutip
  ganda berarti *nama kolom*, jadi query dashboard langsung error.
- `database/migrations/2026_07_20_075556_*.php:17` memakai
  `ALTER TABLE ... MODIFY ... ENUM`, sintaks khas MySQL.

Memaksa PostgreSQL berarti menulis ulang bagian-bagian itu. Jauh lebih murah
memakai MySQL gratis dari penyedia lain.

---

## 1. Siapkan database MySQL gratis (Aiven)

Render tidak punya MySQL, jadi databasenya "menumpang" di luar. Aiven memberi
paket gratis MySQL tanpa kartu kredit (±5 GB, jauh melebihi kebutuhan sistem ini).

1. Daftar di <https://console.aiven.io> → **Create service** → **MySQL**.
2. Pilih **Free plan**. Region: yang paling dekat, misalnya
   `google-asia-southeast1` (Singapura), supaya sejalan dengan region Render nanti.
3. Tunggu status berubah dari *Rebuilding* menjadi **Running** (±3 menit).
4. Buka tab **Overview**, catat: `Host`, `Port`, `User`, `Password`,
   `Database name` (biasanya `defaultdb`).
5. Di bagian **CA Certificate**, klik **Download** — hasilnya berkas `ca.pem`.
   Simpan, dipakai di Bagian 5.

> Alternatif kalau Aiven tidak bisa dipakai: TiDB Cloud Serverless (kompatibel
> MySQL, gratis 5 GB). Hindari Clever Cloud free (hanya 10 MB) dan db4free
> (sering mati).

### Pindahkan data dari Laragon

Kalau data lokal sudah berisi murid dan pembayaran sungguhan, bawa saja:

```bash
# Di komputer Anda (Laragon), ekspor:
mysqldump -u root art-class > art-class.sql

# Impor ke Aiven (ganti HOST/PORT sesuai tab Overview):
mysql --host=HOST --port=PORT --user=avnadmin --password --ssl-ca=ca.pem \
      defaultdb < art-class.sql
```

Kalau ingin mulai dari nol, lewati langkah ini — migrasi dan seeder dijalankan
otomatis di Bagian 6 dan 7.

---

## 2. Buat APP_KEY dan push repositori

```bash
# Kunci enkripsi khusus production. Jangan pakai kunci yang sama dengan lokal.
php artisan key:generate --show
```

Salin hasilnya (bentuknya `base64:...`) ke tempat aman — akan ditempel di Render.

Lalu kirim berkas Docker ke GitHub:

```bash
git add Dockerfile .dockerignore docker/ render.yaml docs/deploy-render.md
git commit -m "Tambah konfigurasi deploy Docker untuk Render"
git push origin main
```

---

## 3. Buat Web Service di Render

1. Masuk ke <https://dashboard.render.com> → **New +** → **Web Service**.
2. **Source Code**: hubungkan GitHub, pilih `harist13/tarakan-art-class`.
3. Isi formulirnya:

   | Kolom | Isi |
   |---|---|
   | Name | `tarakan-art-class` (menentukan URL `tarakan-art-class.onrender.com`) |
   | Project | boleh dikosongkan |
   | **Language** | **Docker** ← ganti dari tebakan "Node" |
   | Branch | `main` |
   | Region | **Singapore** (paling dekat ke Tarakan) |
   | Root Directory | kosongkan |
   | Dockerfile Path | `./Dockerfile` (biasanya terisi otomatis) |
   | Instance Type | **Free** |

4. **Jangan klik Deploy dulu.** Buka **Advanced** dan isi environment variable
   (Bagian 4) serta Secret File (Bagian 5) lebih dulu — deploy pertama tanpa
   database pasti gagal.

### 3b. Cara singkat: Blueprint

Sebagai ganti langkah 1–3, bisa juga **New +** → **Blueprint** → pilih repo ini.
Render membaca `render.yaml` dan membuat layanan dengan region, health check,
serta variabel non-rahasia yang sudah terisi. Variabel rahasia tetap diketik manual.

---

## 4. Environment variables

Di **Advanced → Add Environment Variable**, masukkan satu per satu.

### Wajib

| Key | Value | Catatan |
|---|---|---|
| `APP_NAME` | `Tarakan Art Class` | |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **Kritis.** `true` membocorkan server key Midtrans di halaman error. |
| `APP_KEY` | `base64:...` | dari Bagian 2 |
| `APP_URL` | `https://tarakan-art-class.onrender.com` | **Harus lengkap dengan `https://`.** Salah tulis membuat tautan bayar di WhatsApp mati. |
| `APP_LOCALE` | `id` | |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | host Aiven | misalnya `mysql-xxxx.aivencloud.com` |
| `DB_PORT` | port Aiven | biasanya 5 digit, **bukan** 3306 |
| `DB_DATABASE` | `defaultdb` | |
| `DB_USERNAME` | `avnadmin` | |
| `DB_PASSWORD` | password Aiven | |
| `MYSQL_ATTR_SSL_CA` | `/etc/secrets/ca.pem` | dibaca `config/database.php:62`; berkasnya dibuat di Bagian 5 |
| `SESSION_DRIVER` | `database` | driver `file` akan kehilangan sesi login tiap restart |
| `CACHE_STORE` | `database` | alasan sama |
| `QUEUE_CONNECTION` | `database` | |
| `LOG_CHANNEL` | `stderr` | supaya log tampil di tab Logs Render, bukan hilang di dalam container |
| `LOG_LEVEL` | `warning` | |

### Midtrans

| Key | Value |
|---|---|
| `MIDTRANS_SERVER_KEY` | server key dari Dashboard Midtrans |
| `MIDTRANS_CLIENT_KEY` | client key |
| `MIDTRANS_IS_PRODUCTION` | `false` untuk uji coba, `true` saat siap uang asli |
| `MIDTRANS_ENABLED_PAYMENTS` | `bca_va,bni_va,bri_va,cimb_va,permata_va,other_va,echannel` |

> Jangan menebak sandbox/production dari awalan kunci. Kunci sandbox tidak selalu
> berawalan `SB-`. Ambil langsung dari tab yang benar di Dashboard Midtrans.

### Website publik

Salin nilainya dari `.env.example` bagian "Website Publik" dan "SEO":
`SITE_WHATSAPP`, `SITE_WHATSAPP_DISPLAY`, `SITE_EMAIL`, `SITE_INSTAGRAM`,
`SITE_ADDRESS`, `SITE_ADDRESS_STREET`, `SITE_ADDRESS_CITY`, `SITE_ADDRESS_REGION`,
`SITE_GEO_LAT`, `SITE_GEO_LNG`, `SITE_GOOGLE_VERIFICATION`.

### Khusus container ini

| Key | Value | Fungsi |
|---|---|---|
| `RUN_MIGRATIONS` | `true` | jalankan `migrate --force` tiap start (aman diulang) |
| `RUN_SCHEDULER` | `false` | diaktifkan di Bagian 8 |
| `SEED_CLASS` | *(kosong)* | diisi sekali saja di Bagian 7 |
| `STARTUP_COMMAND` | *(kosong)* | pengganti Shell, lihat Bagian 7 |

---

## 5. Secret File untuk sertifikat SSL Aiven

Aiven menolak koneksi tanpa TLS. Berkas `ca.pem` tidak boleh ikut masuk Git,
jadi titipkan ke Render:

1. Masih di **Advanced** → **Add Secret File**.
2. **Filename**: `ca.pem`
3. **Contents**: tempel seluruh isi `ca.pem` yang diunduh di Bagian 1, termasuk
   baris `-----BEGIN CERTIFICATE-----` dan `-----END CERTIFICATE-----`.

Render menaruhnya di `/etc/secrets/ca.pem` — persis nilai `MYSQL_ATTR_SSL_CA` tadi.

---

## 6. Deploy pertama

Klik **Deploy Web Service**. Yang terjadi, berurutan:

1. **Build** (5–10 menit untuk kali pertama): Docker memasang ekstensi PHP
   (`pdo_mysql`, `gd`, `zip`, `bcmath`, `exif`, `opcache`) lalu `composer install`.
2. **Start**: `docker/entrypoint.sh` membuat direktori `storage`, membuat symlink
   `public/storage`, menjalankan `php artisan migrate --force`, lalu
   `config:cache`, `route:cache`, `view:cache`.
3. **Health check**: Render memanggil `/up` (route bawaan Laravel, terdaftar di
   `bootstrap/app.php:13`). Begitu menjawab 200, status berubah jadi **Live**.

Pantau tab **Logs**. Tanda sehat: baris `==> php artisan migrate --force` diikuti
daftar migrasi, lalu `Your service is live`.

Buka `https://tarakan-art-class.onrender.com` — halaman publik harus muncul.

---

## 7. Membuat user admin tanpa Shell

Paket gratis tidak punya Shell, jadi perintah sekali-pakai dijalankan lewat
variabel. Lewati bagian ini kalau data sudah diimpor dari Laragon di Bagian 1.

1. **Environment** → tambah `SEED_CLASS` = `RolePermissionSeeder` → **Save**.
   Render otomatis restart dan menjalankan seeder itu.
2. Cek Logs, harus ada: `==> php artisan db:seed --class=RolePermissionSeeder`.
3. **Hapus kembali** variabel `SEED_CLASS` supaya tidak ikut jalan tiap restart.

Seeder itu membuat dua akun berpassword `password`
(`database/seeders/RolePermissionSeeder.php:111`):

| Email | Peran |
|---|---|
| `superadmin@tarakanart.com` | super_admin |
| `admin@tarakanart.com` | admin |

**Segera login dan ganti kedua password itu.** Akun default berpassword
`password` di internet terbuka adalah pintu tanpa kunci.

Untuk perintah artisan lain, pakai `STARTUP_COMMAND`, misalnya
`STARTUP_COMMAND=students:suspend-overdue`. Isi → tunggu restart → kosongkan lagi.

---

## 8. Menjaga layanan hidup dan menjalankan penjadwal

`routes/console.php:13` menjadwalkan `students:suspend-overdue` setiap pagi pukul
06.00. Cron Render berbayar, jadi caranya begini: jaga container tetap hidup
dengan ping berkala, lalu biarkan penjadwal Laravel berjalan di dalamnya.

1. Set environment variable `RUN_SCHEDULER` = `true` (entrypoint akan menjalankan
   `php artisan schedule:work` di latar belakang).
2. Set `APP_TIMEZONE` = `Asia/Makassar` supaya "06:00" berarti waktu Tarakan
   (WITA), bukan UTC.
3. Daftar gratis di <https://cron-job.org> → **Create cronjob**:
   - URL: `https://tarakan-art-class.onrender.com/up`
   - Jadwal: setiap **10 menit**
4. Simpan.

Ping tiap 10 menit membuat instance tidak pernah menganggur sampai 15 menit,
sehingga: tidak ada cold start bagi pengunjung, webhook Midtrans selalu disambut
container yang hidup, dan penjadwal harian benar-benar jalan.

**Perhitungan kuota:** hidup terus-menerus = ±720 jam/bulan, sedangkan jatah
gratis 750 jam/bulan. Muat, tapi hanya kalau ini **satu-satunya** layanan gratis
di akun Render Anda.

---

## 9. Foto karya murid akan hilang — dan cara mengatasinya

`ArtworkController.php:113` menyimpan foto ke `storage/app/public/artworks`,
yaitu di dalam container. Paket gratis Render tidak punya disk permanen, jadi
foto itu **hilang setiap container restart atau bangun dari tidur**.

Tiga pilihan:

**a. Biarkan (hanya untuk uji coba).** Semua fitur lain jalan normal; hanya
galeri karya yang kosong setelah restart.

**b. Pindah ke object storage gratis (disarankan untuk pemakaian sungguhan).**
Cloudflare R2 memberi 10 GB gratis. Langkahnya:

1. `composer require league/flysystem-aws-s3-v3`
2. Di `config/filesystems.php`, ubah disk `public` agar memakai driver `s3`
   dengan endpoint R2 dan `'visibility' => 'public'`.
3. Ubah satu baris di `app/Models/Artwork.php:80`:

   ```php
   // dari:
   return asset('storage/'.$this->photo_path);
   // menjadi:
   return Storage::disk('public')->url($this->photo_path);
   ```

   Perubahan ini tidak mengubah apa pun di Laragon — disk lokal menghasilkan URL
   yang sama persis.
4. Tambah env `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`,
   `AWS_ENDPOINT`, `AWS_URL` di Render.

**c. Naik ke instance berbayar Render** (mulai $7/bulan) lalu pasang Disk 1 GB di
`/var/www/html/storage/app/public`. Ini sekaligus menghapus masalah cold start.

---

## 10. Sambungkan Midtrans ke URL baru

1. Dashboard Midtrans → **Settings → Configuration**.
2. **Payment Notification URL**:
   `https://tarakan-art-class.onrender.com/midtrans/notification`
3. **Finish / Unfinish / Error Redirect URL**: arahkan ke `APP_URL` Anda.
4. Lakukan satu pembayaran uji. Tanda berhasil ada di database, bukan di log:
   kolom `gateway_status`, `gateway_payment_type`, dan `paid_at` di tabel
   `payments` harus terisi.

Sandbox dan Production punya URL notifikasi **terpisah** — mengisi yang satu
tidak mengisi yang lain.

---

## 11. Domain sendiri (gratis, TLS otomatis)

1. **Settings → Custom Domains → Add Custom Domain**, misalnya `tarakanartclass.com`.
2. Render menampilkan record DNS. Di penyedia domain, tambahkan:
   - `CNAME` untuk `www` → `tarakan-art-class.onrender.com`
   - `A` untuk domain utama → alamat IP yang Render tampilkan
3. Tunggu verifikasi; sertifikat TLS dibuat otomatis.
4. **Perbarui `APP_URL`** ke domain baru, dan perbarui juga Notification URL di
   Midtrans. Kalau lupa, tautan `/bayar/{token}` yang dikirim lewat WhatsApp akan
   menunjuk ke alamat lama.

---

## 12. Kalau ada masalah

| Gejala | Penyebab yang paling sering |
|---|---|
| Build gagal di `composer install` | `composer.lock` tidak sinkron dengan `composer.json`. Jalankan `composer update --lock` di lokal, commit, push. |
| Deploy "Live" tapi halaman 500 | Hampir selalu database. Cek Logs: `SQLSTATE[HY000] [2002]` = host/port salah; `Connections using insecure transport are prohibited` = `MYSQL_ATTR_SSL_CA` atau Secret File salah. |
| `No application encryption key` | `APP_KEY` belum diisi atau tanpa awalan `base64:`. |
| Halaman error memamerkan isi `.env` | `APP_DEBUG` masih `true`. Ganti sekarang juga. |
| Semua halaman 404 kecuali beranda | Konfigurasi nginx tidak terbaca — pastikan `docker/nginx.conf.template` ikut ter-commit. |
| Aset/CSS hilang, halaman polos | `APP_URL` salah tulis (misalnya `http:localhost`). Harus lengkap: `https://domain-anda`. |
| Deploy pertama sangat lama | Normal, 5–10 menit. Deploy berikutnya lebih cepat karena layer Docker di-cache. |
| Kunjungan pertama lambat ~50 detik | Instance sedang bangun tidur. Atasi dengan Bagian 8. |
| Foto karya menghilang | Perilaku yang memang diharapkan di paket gratis. Lihat Bagian 9. |
| Perubahan env tidak berpengaruh | Config di-cache saat start. Simpan env → Render restart → cache dibuat ulang. Kalau ragu: **Manual Deploy → Clear build cache & deploy**. |

Log ada di tab **Logs**; riwayat build di tab **Events**.

---

## 13. Daftar periksa sebelum dipakai sungguhan

- [ ] `APP_DEBUG=false` dan `APP_ENV=production`
- [ ] `APP_URL` memakai `https://` dan domain yang benar
- [ ] Password `superadmin@tarakanart.com` dan `admin@tarakanart.com` sudah diganti
- [ ] Notification URL Midtrans menunjuk ke domain production
- [ ] `MIDTRANS_IS_PRODUCTION=true` dan kunci production terpasang
- [ ] Satu pembayaran uji terbukti masuk (`paid_at` terisi di tabel `payments`)
- [ ] Ping cron-job.org aktif kalau penjadwal harian diandalkan
- [ ] Keputusan soal foto karya sudah diambil (Bagian 9)
- [ ] Cadangan database Aiven dijadwalkan atau diunduh berkala
