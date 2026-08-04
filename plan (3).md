# Rencana Website Publik — Tarakan Art Class

> Dokumen perencanaan untuk website publik (marketing & informasi) Tarakan Art Class.
> Website ini **terpisah** dari sistem manajemen (admin) dan aplikasi wali murid — tugasnya
> menarik & meyakinkan orang tua calon murid, lalu mengarahkan ke pendaftaran/kontak.
>
> **Catatan:** halaman login sudah tersedia, jadi **tidak** termasuk dalam rencana ini.

---

## 1. Ringkasan

| Aspek | Keterangan |
|---|---|
| Tujuan | Menarik calon murid, membangun kepercayaan, mengarahkan ke pendaftaran |
| Audiens utama | Orang tua anak (usia pra-sekolah s/d SD) di sekitar Tarakan |
| Audiens sekunder | Wali murid aktif (cek jadwal & info) |
| Aksi utama (goal) | Klik "Daftar" / menghubungi via WhatsApp |
| Kesan yang dituju | Kreatif, ramah anak, tapi tetap profesional dan terpercaya |

---

## 2. Pendekatan Teknis

- **Stack:** Laravel (Blade) + bootstrap, disatukan dalam project yang sama dengan sistem.
- **Routing:** route publik `/` s/d `/kontak`; halaman login yang sudah ada tetap di jalurnya sendiri.
- **Satu domain, satu deploy** — komponen Blade (navbar, footer, kartu) dibuat reusable via `@include` / komponen Blade.
- **Responsive:** desktop, tablet, mobile (mengacu non-functional requirement PRD).
- **Performa:** target < 3 detik load (sesuai PRD); optimasi gambar galeri (lazy-load, format webp).
- **SEO dasar:** meta title/description per halaman, Open Graph, sitemap.xml, alt text gambar.
- **Elemen global:** tombol WhatsApp mengambang (floating) di semua halaman.

---

## 3. Sistem Desain

Konsisten dengan landing page yang sudah dibuat (tema "kotak krayon").

### Warna
| Nama | Hex | Pemakaian |
|---|---|---|
| Ink | `#241B36` | Teks utama, tombol gelap |
| Paper | `#FDFAF3` | Latar utama |
| Paper 2 | `#F6EFE2` | Latar section selang-seling |
| Coral | `#FF6B5E` | Aksen utama / brand |
| Sun | `#FDBB2D` | Aksen sekunder, highlight |
| Sky | `#3FA7E0` | Aksen, ikon |
| Leaf | `#5BBE7A` | Status positif, aksen |
| Grape | `#8B6FD1` | Aksen tambahan |

### Tipografi
- **Display / judul:** Baloo 2 (rounded, ramah anak) — dipakai untuk heading & tombol.
- **Body:** Plus Jakarta Sans — untuk paragraf & UI.

### Komponen global (dibuat sekali, dipakai ulang)
- Navbar sticky (logo + menu + tombol "Daftar").
- Footer (logo, menu ringkas, kontak, sosial media, kredit "Dibangun oleh Manufindo").
- Tombol: primary (ink), ghost (outline), pill.
- Kartu (feature card, kelas card, artikel card) dengan aksen krayon.
- Tombol WhatsApp mengambang.

---

## 4. Struktur Situs (Sitemap)

| Halaman | Route | Prioritas |
|---|---|---|
| Home (landing) | `/` | Wajib |
| Tentang | `/tentang` | Wajib |
| Program & Kelas | `/program` | Wajib |
| Galeri | `/galeri` | Wajib |
| Jadwal | `/jadwal` | Sebaiknya |
| Blog / Tips | `/blog` | Opsional (fase 2) |
| Kontak & Pendaftaran | `/kontak` | Wajib |

---

## 5. Rincian Halaman

### 5.1 Home — `/`
**Tujuan:** menyaring pengunjung menuju Program atau Kontak. *(Sudah dibuat sebagai landing page.)*

Section (urut dari atas):
1. Hero — headline + ajakan daftar + mockup/ilustrasi.
2. Program unggulan — cuplikan 3–4 kelas.
3. Kenapa Tarakan Art Class — poin keunggulan.
4. Preview galeri karya murid.
5. Testimoni orang tua.
6. Cuplikan artikel terbaru (jika Blog aktif).
7. CTA penutup — "Daftar sekarang".

---

### 5.2 Tentang — `/tentang`
**Tujuan:** membangun kepercayaan orang tua.

Section:
- Cerita di balik TAC (latar belakang, untuk usia berapa).
- Visi & misi.
- Metode belajar yang membedakan.
- Profil tutor (foto + nama + spesialisasi).
- Foto fasilitas / studio.
- Angka pencapaian (jumlah murid/alumni) — opsional.

---

### 5.3 Program & Kelas — `/program`
**Tujuan:** halaman konversi utama.

Section:
- Intro singkat jenis kelas.
- Kartu per kelas (Preschool, Coloring, Drawing, + Holiday Class musiman), tiap kartu berisi:
  - Deskripsi singkat
  - Rentang usia
  - Durasi & kapasitas
  - Harga (mis. Rp250.000 / bulan)
  - Jadwal umum
  - Tombol **"Daftar kelas ini"** → ke `/kontak` dengan kelas terpilih.
- FAQ singkat seputar kelas (opsional).

---

### 5.4 Galeri — `/galeri`
**Tujuan:** bukti sosial visual.

Section:
- Grid karya murid dengan filter per kategori kelas.
- Foto kegiatan & dokumentasi event/pameran.
- Embed / feed Instagram (memanfaatkan field Instagram pada data murid).

---

### 5.5 Jadwal — `/jadwal`
**Tujuan:** halaman yang sering dibuka wali aktif.

Section:
- Jadwal kelas mingguan (tabel / kalender sederhana).
- Info kelas pengganti (replacement class).
- Pengumuman Holiday Class.

---

### 5.6 Blog / Tips — `/blog` *(fase 2, opsional)*
**Tujuan:** SEO & menjaga situs tetap "hidup".

Section:
- Daftar artikel (kartu: gambar, judul, ringkasan).
- Halaman detail artikel.
- Kategori / tag (tips di rumah, manfaat menggambar, cerita kegiatan).

---

### 5.7 Kontak & Pendaftaran — `/kontak`
**Tujuan:** menampung calon murid & pertanyaan.

Section:
- Peta lokasi + alamat.
- Jam operasional.
- Nomor WhatsApp & tautan Instagram.
- Form pendaftaran (nama anak, usia, nama & kontak orang tua, pilih kelas).

> **Catatan PRD:** "Pendaftaran Online Publik" masih *out of scope*. Jadi form ini
> **mengirim data calon murid ke admin** (via email/WhatsApp), **bukan** self-service
> pendaftaran penuh. Pendaftaran final tetap diproses admin di sistem.

---

## 6. Catatan dari PRD

- **Pendaftaran publik out of scope** → form Kontak berfungsi sebagai lead, diproses admin.
- **Payment gateway** (QRIS, VA, Midtrans, Xendit) berada di sisi sistem/app, bukan di website publik.
- Website publik bersifat **informasi & marketing** — semua transaksi & data operasional tetap di sistem admin.

---

## 7. Fase Pembuatan (Prioritas)

**Fase 1 — Fondasi & konversi**
1. Template dasar: navbar + footer + layout + sistem desain (bootstrap warna & font).
2. Home (integrasi landing page yang sudah ada).
3. Program & Kelas.
4. Kontak & Pendaftaran.

**Fase 2 — Kepercayaan & info**
5. Tentang.
6. Galeri.
7. Jadwal.

**Fase 3 — Pengembangan**
8. Blog / Tips.
9. Optimasi SEO & performa lanjutan.

---

## 8. Langkah Berikutnya

- [x] Setup bootstrap (warna & font sistem desain) — `public/css/site.css`.
- [x] Buat komponen global (navbar, footer, tombol, kartu, WA float).
- [x] Bangun Home dari landing page yang sudah ada.
- [x] Bangun Program & Kelas + Kontak (jalur konversi).
- [x] Siapkan endpoint form Kontak → email/WhatsApp admin.
- [x] Lanjut Tentang, Galeri, Jadwal.
- [ ] Blog / Tips — fase 3, belum dikerjakan.
- [ ] Isi konten asli: data kontak di `.env`, foto galeri & tutor, `public/images/og-image.jpg`.
